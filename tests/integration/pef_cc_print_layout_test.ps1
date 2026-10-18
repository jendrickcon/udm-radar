param(
    [Parameter(Mandatory=$true)][string]$SampleDirectory,
    [Parameter(Mandatory=$true)][string]$OutputDirectory,
    [Parameter(Mandatory=$true)][string]$Python,
    [Parameter(Mandatory=$true)][string]$Printer,
    [double]$MinimumDataFontPoints = 8
)
# Opt-in, synthetic-only native Excel review. No database, printer job, or workbook save.
# Generate samples first with pefTestPrintSamples() in a fresh private directory.
$ErrorActionPreference = 'Stop'
if($MinimumDataFontPoints -lt 8){throw 'The current project print-review floor cannot be weakened below 8pt.'}
$samplesRoot = (Resolve-Path -LiteralPath $SampleDirectory).Path
$outputRoot = [IO.Path]::GetFullPath($OutputDirectory)
foreach ($path in @($samplesRoot,$outputRoot)) {
    if ($path -match '[\\/]htdocs(?:[\\/]|$)') { throw 'Print evidence must be outside web roots.' }
}
if (Test-Path -LiteralPath $outputRoot) { throw 'Use a new output directory; previous print evidence is immutable.' }
$matrix = Get-Content -Raw -LiteralPath (Join-Path $samplesRoot 'samples.json') | ConvertFrom-Json
if($matrix.matrix -ne 'sd-section-membership-wp1.3' -or $matrix.template_version -ne 'v0.2-r3'){throw 'Use the r3 section-membership operational matrix, with excessive sections rejected before export.'}
$samples = $matrix.cases
$required = @('normal','longest-section','irregular-marker','blank','capacity','longest-title','catalogue-1','catalogue-2','catalogue-3','catalogue-4','catalogue-5')
if ((@($samples.PSObject.Properties.Name | Sort-Object) -join ',') -ne (@($required | Sort-Object) -join ',')) { throw 'The complete synthetic stress matrix is required.' }
New-Item -ItemType Directory -Path $outputRoot | Out-Null
$excel=$null; $book=$null; $results=@()
try {
    $excel=New-Object -ComObject Excel.Application
    $excel.Visible=$false; $excel.DisplayAlerts=$true; $excel.AutomationSecurity=3
    foreach ($name in $required) {
        $sample=$samples.$name
        $path=(Resolve-Path -LiteralPath $sample.path).Path
        if ([IO.Path]::GetDirectoryName($path) -ne $samplesRoot) { throw 'Sample escaped the private sample directory.' }
        $hash=(Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($hash -ne $sample.sha256) { throw 'Sample hash mismatch.' }
        $book=$excel.Workbooks.Open($path,0,$true)
        # Excel requires a workbook before selecting this Folio-capable driver.
        # Application.ActivePrinter does not change the Windows default printer.
        $excel.ActivePrinter=$Printer
        $excel.CalculateFullRebuild()
        if ((@($book.Worksheets | ForEach-Object {$_.Name}) -join ',') -ne 'PEF,CC') { throw 'Unexpected sheets.' }
        $entry=@{case=$name;sha256=$hash;read_only=$book.ReadOnly;sheets=@();fields=@()}
        foreach ($sheet in $book.Worksheets) {
            $page=$sheet.PageSetup
            $pdf=Join-Path $outputRoot ($name+'-'+$sheet.Name+'.pdf')
            $sheet.ExportAsFixedFormat(0,$pdf,0,$false,$false)
            $entry.sheets+=@{name=$sheet.Name;pdf=$pdf;paper_size=$page.PaperSize;orientation=$page.Orientation;
                print_area=$page.PrintArea;zoom=$page.Zoom;fit_wide=$page.FitToPagesWide;fit_tall=$page.FitToPagesTall;
                margins_points=@{left=$page.LeftMargin;right=$page.RightMargin;top=$page.TopMargin;bottom=$page.BottomMargin};
                headers=@($page.LeftHeader,$page.CenterHeader,$page.RightHeader);footers=@($page.LeftFooter,$page.CenterFooter,$page.RightFooter)}
        }
        $pef=$book.Worksheets.Item('PEF')
        $cc=$book.Worksheets.Item('CC')
        $entry.cc_name=[string]$cc.Range('B1').Value2
        $anchor=$pef.Range('A49:G49')
        $entry.anchor=@{left=$anchor.Left;top=$anchor.Top;width=$anchor.Width;height=$pef.Range('A49:G52').Height}
        $entry.section_wrap=$pef.Range('A51').WrapText
        $entry.section_row_height=$pef.Rows.Item(51).RowHeight
        $entry.section_label_height=$pef.Rows.Item(52).RowHeight
        $entry.section_font=$pef.Range('A51').Font.Size
        $entry.section_horizontal=$pef.Range('A51').HorizontalAlignment
        $entry.section_vertical=$pef.Range('A51').VerticalAlignment
        $entry.section_merge=$pef.Range('A51').MergeArea.Address()
        $entry.section_label_merge=$pef.Range('A52').MergeArea.Address()
        $entry.proposed_row_heights=@(54..65 | ForEach-Object {$pef.Rows.Item($_).RowHeight})
        $cells=@('A10','A12','A51')
        foreach($row in 23..35){$cells+=@(('A'+$row),('B'+$row))}
        foreach($row in 54..65){$cells+=@(('A'+$row),('B'+$row))}
        foreach($address in $cells){
            $cell=$pef.Range($address);$area=$cell.MergeArea;$value=[string]$cell.Value2
            if($value -eq ''){continue}
            $entry.fields+=@{cell=$address;value=$value;left=$area.Left;top=$area.Top;width=$area.Width;height=$area.Height;font_points=$cell.Font.Size}
        }
        $results+=$entry
        $book.Close($false);[void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($book);$book=$null
        if((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant() -ne $hash){throw 'Read-only review changed a sample.'}
    }
    @{excel_version=$excel.Version;excel_build=$excel.Build;active_printer=$excel.ActivePrinter;minimum_data_font_points=$MinimumDataFontPoints;cases=$results;defensive_rejections=$matrix.defensive_rejections;operational_metrics=$matrix.metrics} |
        ConvertTo-Json -Depth 12 | Set-Content -LiteralPath (Join-Path $outputRoot 'native-excel.json') -Encoding UTF8
} finally {
    if($null -ne $book){$book.Close($false);[void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($book)}
    if($null -ne $excel){$excel.Quit();[void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($excel)}
    [GC]::Collect();[GC]::WaitForPendingFinalizers()
}
# Text presence alone is insufficient: Excel's PDF text layer can contain clipped
# glyphs. Compare every matched glyph bounding box against its native cell area.
$audit=@'
import json, re, sys
from pathlib import Path
import pdfplumber
root=Path(sys.argv[1]); native=json.loads((root/'native-excel.json').read_text(encoding='utf-8-sig'))
checks=[]
def check(ok, case, detail):
    checks.append({'passed':bool(ok),'case':case,'detail':detail})
for case in native['cases']:
    name=case['case']
    check(case['read_only'] and not case['section_wrap'] and case['section_row_height']==19.5, name, 'Read-only workbook; original 19.5pt section row with wrapping off')
    check(case['section_label_height']==12 and case['section_font']==18 and case['section_horizontal']==-4108 and case['section_vertical']==-4108, name, 'Original 12pt label row and centered 18pt section font')
    check(case['section_merge']=='$A$51:$G$51' and case['section_label_merge']=='$A$52:$G$52' and case['proposed_row_heights']==[12]*12,name,'Separate section/label merges and all twelve original-height subject slots')
    for sheet in case['sheets']:
        check(sheet['paper_size']==14 and sheet['orientation']==1 and sheet['zoom'] is False and sheet['fit_wide']==sheet['fit_tall']==1, name, sheet['name']+' native Folio portrait / one-page settings')
        expected='$A$1:$G$80' if sheet['name']=='PEF' else '$A$1:$O$73'
        check(sheet['print_area']==expected and not any(sheet['headers']+sheet['footers']), name, sheet['name']+' bounded print area and empty headers/footers')
        with pdfplumber.open(sheet['pdf']) as pdf:
            check(len(pdf.pages)==1, name, sheet['name']+' exports to one page')
            page=pdf.pages[0]
            check(abs(page.width-612)<0.1 and abs(page.height-936)<0.1, name, sheet['name']+' actual 8.5 x 13 inch PDF')
            text_chars=[c for c in page.chars if not c['text'].isspace()]
            minimum=min((c['size'] for c in text_chars),default=0)
            check(minimum>=native['minimum_data_font_points'],name,sheet['name']+' all displayed text >= '+str(native['minimum_data_font_points'])+'pt (observed '+str(round(minimum,2))+')')
            if sheet['name']!='PEF':
                cc_chars=[c for c in page.chars if not c['text'].isspace()]
                cc_flat=''.join(c['text'] for c in cc_chars)
                for label,value in [('Student name',case['cc_name']),('active GED101 code','GED101'),('active Physical Activity title prefix','Physical Activity')]:
                    needle=re.sub(r'\s','',value);pos=cc_flat.find(needle)
                    check(pos>=0,name,'CC '+label+' complete value located')
                    if pos>=0:
                        size=min(c['size'] for c in cc_chars[pos:pos+len(needle)])
                        check(size>=native['minimum_data_font_points'],name,'CC '+label+' font >= '+str(native['minimum_data_font_points'])+'pt (observed '+str(round(size,2))+')')
                continue
            # The retained filled target-term/section panel is the coordinate
            # anchor. Infer the printer's scale from its native merged width.
            labels=[w for w in page.extract_words() if w['text']=='(Section)']
            check(len(labels)==1,name,'Unique section label below the section value')
            panels=[r for r in page.rects if r['width']>350 and labels and r['top']<=labels[0]['top'] and r['bottom']>=labels[0]['bottom']]
            check(len(panels)==1, name, 'Unique native target-panel coordinate anchor')
            if len(panels)!=1: continue
            panel=panels[0]; anchor=case['anchor']; scale_x=panel['width']/anchor['width']; scale_y=panel['height']/anchor['height']
            # Excel quantizes printed row heights independently of column
            # widths. Use actual PDF grid borders rather than a guessed
            # uniform transform of worksheet Range.Top coordinates.
            lines=sorted({round(r['top'],2) for r in page.rects if r['width']>350 and r['height']<1})
            groups=[]
            for y in lines:
                if groups and abs((y-groups[-1][-1])-12*scale_y)<0.8: groups[-1].append(y)
                else: groups.append([y])
            grids=[g for g in groups if len(g)>=13]
            recorded=next((g for g in grids if g[-1]<panel['top']),[])
            proposed=next((g for g in grids if g[0]>panel['bottom']),[])
            check(len(recorded)==14 and len(proposed)==13,name,'Both capacity grids retain every row border')
            section_top=next((y for y in lines if abs(y-(panel['top']+31.5*scale_y))<1),None)
            chars=[c for c in page.chars if not c['text'].isspace()]
            flat=''.join(c['text'] for c in chars)
            for field in case['fields']:
                value=re.sub(r'\s','',field['value'])
                row=int(re.search(r'\d+',field['cell'])[0])
                if row in [10,12]:
                    expected_top=panel['top']+(field['top']-anchor['top'])*scale_y
                    candidates=[r for r in page.rects if r['width']>350 and abs(r['top']-expected_top)<3 and r['height']>=field['height']*scale_y-1]
                    if not candidates:
                        check(False,name,field['cell']+' native printed identity boundary found');continue
                    identity_panel=min(candidates,key=lambda r:abs(r['top']-expected_top))
                    top=identity_panel['top'];bottom=min(identity_panel['bottom'],top+field['height']*scale_y)
                elif 23<=row<=35 and len(recorded)==14:
                    top,bottom=recorded[row-23:row-21]
                elif 54<=row<=65 and len(proposed)==13:
                    top,bottom=proposed[row-54:row-52]
                elif row==51 and section_top is not None:
                    # Use native row geometry, independently of text and horizontal scale.
                    top,bottom=section_top,panel['bottom']-case['section_label_height']*scale_y
                else:
                    check(False,name,field['cell']+' native printed field boundary found');continue
                box=(panel['x0']+(field['left']-anchor['left'])*scale_x,top,
                     panel['x0']+(field['left']-anchor['left']+field['width'])*scale_x,bottom)
                matches=[]; start=0
                while True:
                    pos=flat.find(value,start)
                    if pos<0: break
                    glyphs=chars[pos:pos+len(value)]; start=pos+1
                    middle=(min(c['top'] for c in glyphs)+max(c['bottom'] for c in glyphs))/2
                    if box[1]-2<=middle<=box[3]+2: matches.append(glyphs)
                label=field['cell']
                check(len(matches)==1,name,label+' complete value located in its printed row')
                if len(matches)!=1: continue
                glyphs=matches[0]
                visible=all(c['x0']>=box[0]-1 and c['x1']<=box[2]+1 and c['top']>=box[1]-1 and c['bottom']<=box[3]+1 for c in glyphs)
                size=min(c['size'] for c in glyphs)
                check(visible,name,label+' all glyphs within printed cell / no label or border overlap')
                check(size>=native['minimum_data_font_points'],name,label+' variable-data font >= '+str(native['minimum_data_font_points'])+'pt (observed '+str(round(size,2))+')')
for name,result in native['defensive_rejections'].items():
    check(result['preview_rejected'] and result['export_rejected'] and not result['output_created'],name,'Extreme/out-of-contract value rejected before generating a print file')
out={'assertions':len(checks),'failures':sum(not c['passed'] for c in checks),'checks':checks,'operational_metrics':native['operational_metrics'],
     'limits':'Native Excel PDF geometry plus separate raster review; owner actual Print Preview and physical printer acceptance remain required. The 8pt data floor is a review criterion, not an asserted institutional standard.'}
(root/'print-results.json').write_text(json.dumps(out,indent=2),encoding='utf-8')
for c in checks:
    if not c['passed']: print('[FAIL] '+c['case']+': '+c['detail'])
print('SUMMARY: %d assertions; %d failures.'%(out['assertions'],out['failures']))
sys.exit(1 if out['failures'] else 0)
'@
$audit | & $Python - $outputRoot
exit $LASTEXITCODE
