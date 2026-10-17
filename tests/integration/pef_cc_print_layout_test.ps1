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
$samplesRoot = (Resolve-Path -LiteralPath $SampleDirectory).Path
$outputRoot = [IO.Path]::GetFullPath($OutputDirectory)
foreach ($path in @($samplesRoot,$outputRoot)) {
    if ($path -match '[\\/]htdocs(?:[\\/]|$)') { throw 'Print evidence must be outside web roots.' }
}
if (Test-Path -LiteralPath $outputRoot) { throw 'Use a new output directory; previous print evidence is immutable.' }
$samples = Get-Content -Raw -LiteralPath (Join-Path $samplesRoot 'samples.json') | ConvertFrom-Json
$required = @('normal','blank','section96','wide96','unicode96','capacity','long-text')
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
        $anchor=$pef.Range('A49:G49')
        $entry.anchor=@{left=$anchor.Left;top=$anchor.Top;width=$anchor.Width}
        $entry.section_wrap=$pef.Range('A51').WrapText
        $entry.section_row_height=$pef.Rows.Item(51).RowHeight
        $cells=@('A10','A51')
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
    @{excel_version=$excel.Version;excel_build=$excel.Build;active_printer=$excel.ActivePrinter;minimum_data_font_points=$MinimumDataFontPoints;cases=$results} |
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
    check(case['read_only'] and case['section_wrap'] and case['section_row_height']==96, name, 'Read-only workbook; section wraps in 96pt row')
    for sheet in case['sheets']:
        check(sheet['paper_size']==14 and sheet['orientation']==1 and sheet['zoom'] is False and sheet['fit_wide']==sheet['fit_tall']==1, name, sheet['name']+' native Folio portrait / one-page settings')
        expected='$A$1:$G$80' if sheet['name']=='PEF' else '$A$1:$O$73'
        check(sheet['print_area']==expected and not any(sheet['headers']+sheet['footers']), name, sheet['name']+' bounded print area and empty headers/footers')
        with pdfplumber.open(sheet['pdf']) as pdf:
            check(len(pdf.pages)==1, name, sheet['name']+' exports to one page')
            page=pdf.pages[0]
            check(abs(page.width-612)<0.1 and abs(page.height-936)<0.1, name, sheet['name']+' actual 8.5 x 13 inch PDF')
            if sheet['name']!='PEF': continue
            # The retained filled target-term/section panel is the coordinate
            # anchor. Infer the printer's scale from its native merged width.
            panels=[r for r in page.rects if r['width']>350 and 70<r['height']<130 and 450<r['top']<550]
            check(len(panels)==1, name, 'Unique native target-panel coordinate anchor')
            if len(panels)!=1: continue
            panel=panels[0]; anchor=case['anchor']; scale_x=panel['width']/anchor['width']
            # Excel quantizes printed row heights independently of column
            # widths. Use actual PDF grid borders rather than a guessed
            # uniform transform of worksheet Range.Top coordinates.
            lines=sorted({round(r['top'],2) for r in page.rects if r['width']>350 and r['height']<1})
            groups=[]
            for y in lines:
                if groups and 8 < y-groups[-1][-1] < 10: groups[-1].append(y)
                else: groups.append([y])
            grids=[g for g in groups if len(g)>=13]
            recorded=next((g for g in grids if 250<g[0]<400),[])
            proposed=next((g for g in grids if 600<g[0]<700),[])
            check(len(recorded)==14 and len(proposed)==13,name,'Both capacity grids retain every row border')
            section_top=next((y for y in lines if panel['top']+15<y<panel['top']+35),None)
            name_panel=next((r for r in page.rects if r['width']>350 and 130<r['top']<145 and 20<r['height']<35),None)
            chars=[c for c in page.chars if not c['text'].isspace()]
            flat=''.join(c['text'] for c in chars)
            for field in case['fields']:
                value=re.sub(r'\s','',field['value'])
                row=int(re.search(r'\d+',field['cell'])[0])
                if row==10 and name_panel:
                    top,bottom=name_panel['top'],name_panel['bottom']
                elif 23<=row<=35 and len(recorded)==14:
                    top,bottom=recorded[row-23:row-21]
                elif 54<=row<=65 and len(proposed)==13:
                    top,bottom=proposed[row-54:row-52]
                elif row==51 and section_top is not None:
                    # Determine vertical scale from the unchanged 18pt section
                    # font, independently of the column scale.
                    section_chars=[c for c in page.chars if section_top<c['top']<panel['bottom']-10 and c['size']>10]
                    scale_y=max((c['size'] for c in section_chars),default=0)/field['font_points']
                    top,bottom=section_top,section_top+field['height']*scale_y
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
out={'assertions':len(checks),'failures':sum(not c['passed'] for c in checks),'checks':checks,
     'limits':'Native Excel PDF geometry plus separate raster review; owner actual Print Preview and physical printer acceptance remain required. The 8pt data floor is a review criterion, not an asserted institutional standard.'}
(root/'print-results.json').write_text(json.dumps(out,indent=2),encoding='utf-8')
for c in checks:
    if not c['passed']: print('[FAIL] '+c['case']+': '+c['detail'])
print('SUMMARY: %d assertions; %d failures.'%(out['assertions'],out['failures']))
sys.exit(1 if out['failures'] else 0)
'@
$audit | & $Python - $outputRoot
exit $LASTEXITCODE
