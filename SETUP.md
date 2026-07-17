# UdM-RADAR — Complete Setup Guide

This walks through everything needed to run this project on a fresh Windows
machine, from nothing installed to the app running in a browser. Written for
someone doing this for the first time — skip ahead if a step is already done.

---

## 1. Install XAMPP (Apache + MySQL + PHP + phpMyAdmin)

1. Download from **[apachefriends.org](https://www.apachefriends.org/)** —
   pick the Windows installer, PHP 8.2.x.
2. Run the installer. On the **"Select Components"** screen, you only need:
   - ✅ Apache
   - ✅ MySQL
   - ✅ PHP
   - ✅ phpMyAdmin
   Uncheck Tomcat, Perl, and anything else — this project doesn't use them,
   and skipping them makes the install faster and lighter.
3. Install to the default location (`C:\xampp`).
4. Open **XAMPP Control Panel** (search for it in the Start menu), click
   **Start** next to both **Apache** and **MySQL**. Both rows should turn
   green. If Apache fails to start, something else on your machine
   (commonly Skype, or another dev server) is already using port 80 —
   click **Config → httpd.conf** and change `Listen 80` to `Listen 8080`,
   then remember to use `localhost:8080` everywhere below.

## 2. Install Git

1. Download from **[git-scm.com](https://git-scm.com/downloads)**.
2. Run the installer with defaults — the one screen worth checking is
   **"Adjusting your PATH environment"**, make sure **"Git from the command
   line and also from 3rd-party software"** is selected (it's the default).
3. **Restart your terminal/VS Code completely** after installing — PATH
   changes don't apply to already-open windows.
4. Verify: open a terminal, run `git --version`. Should print a version
   number, not an error.

## 3. Install VS Code

1. Download from **[code.visualstudio.com](https://code.visualstudio.com/)**.
2. Install with defaults.

### Recommended extensions

Open VS Code → Extensions panel (`Ctrl+Shift+X`) → search and install each:

| Extension | Publisher | Why |
|---|---|---|
| **PHP Intelephense** | Ben Mewburn | Autocomplete, error-checking, go-to-definition for PHP |
| **SQLTools** + **SQLTools MySQL/MariaDB driver** | Matheus Teixeira | Browse and query the database directly inside VS Code, no need to alt-tab to phpMyAdmin |
| **GitLens** | GitKraken | See commit history/blame inline — useful once the repo has real history |
| **Python** | Microsoft | Needed once you start working in `python_ml/` |

### One VS Code setting worth changing

`Ctrl+,` to open Settings, search **"files: eol"**, set it to `\n` (LF).
Windows defaults to `\r\n` (CRLF), which can cause noisy whole-file diffs in
Git if you and a teammate use different OSes. Not critical solo, but a good
habit.

---

## 4. Get the project running

### Clone the repo

Open a terminal in VS Code (`` Ctrl+` ``), navigate into XAMPP's web root,
and clone there directly so Apache can serve it:

```bash
cd C:\xampp\htdocs
git clone https://github.com/jendrickcon/udm-radar.git
cd udm-radar
```

### Set up config files

The real config files are gitignored (see `README.md` for why) — copy the
example templates and fill in real values:

```bash
copy config\db.example.php config\db.php
copy config\mail.example.php config\mail.php
```

(`copy` is the Windows/PowerShell equivalent of `cp`.) Default XAMPP values
in `db.example.php` — `root` user, empty password — work out of the box for
local development; you shouldn't need to change anything in `db.php` unless
your MySQL setup differs from a fresh XAMPP install.

### Import the database

The repo includes one consolidated export — `database/udm_radar.sql` —
containing the full schema and all current data in one file. You do **not**
need to replay this project's build history (seed data, name migration,
grade patches, etc.) — those were only relevant while the data was being
built; a fresh setup just needs the one finished export.

1. Open **[localhost/phpmyadmin](http://localhost/phpmyadmin)** in a browser
   (Apache + MySQL must be running from step 1).
2. Click **New** in the left sidebar → name the database `udm_radar` →
   Create.
3. Click into the new `udm_radar` database → **Import** tab → choose
   `database/udm_radar.sql` from the cloned repo → **Go**.

**Keeping this file current:** whenever you make a meaningful change to
your live database (new patch applied, new data added), re-export it —
phpMyAdmin's `udm_radar` database page → **Export** tab → **Quick** export
method, **SQL** format → **Go** — and replace `database/udm_radar.sql` in
the repo, then commit. That way the repo always reflects a working,
importable snapshot, not a trail of incremental patches someone would have
to reconstruct in order.

### Open the app

Visit **[localhost/udm-radar/login.php](http://localhost/udm-radar/login.php)**.
You should see the login page. If you get a blank page or a PHP error
instead, check XAMPP Control Panel's Apache **Logs** button for the actual
error message.

---

## 5. Python setup (for `python_ml/`, once that pipeline exists)

The Decision Tree prediction pipeline isn't built yet as of this writing —
this section is here so it's ready when it is.

1. Check Python is installed: `python --version` in a terminal. If missing,
   install from **[python.org](https://www.python.org/downloads/)** — on
   the first installer screen, check **"Add python.exe to PATH"** before
   clicking Install (easy to miss, and without it you'll get the same
   "not recognized" error Git gave before installing).
2. Create a virtual environment inside `python_ml/` so its packages don't
   clash with anything else on your machine:
   ```bash
   cd python_ml
   python -m venv venv
   venv\Scripts\activate
   ```
   Your terminal prompt should now show `(venv)` at the start of the line.
3. Install dependencies (once a `requirements.txt` exists in that folder):
   ```bash
   pip install -r requirements.txt
   ```
   Expected packages for this pipeline: `flask`, `scikit-learn`, `pandas`,
   `numpy` — if there's no `requirements.txt` yet, `pip install flask
   scikit-learn pandas numpy` covers the basics described in this project's
   design (Flask API + `DecisionTreeClassifier`).
4. Remember to `venv\Scripts\activate` again every time you open a new
   terminal to work in this folder — the virtual environment doesn't stay
   active across terminal sessions.

---

## Troubleshooting quick reference

| Symptom | Likely cause |
|---|---|
| `git`/`python` "not recognized" | Not installed, or installed but terminal wasn't restarted after |
| Apache won't start (red in XAMPP) | Port 80 already in use — see step 1.4 |
| Blank white page at localhost/udm-radar | PHP error being swallowed — check Apache error log via XAMPP Control Panel |
| `#1045 Access denied` on DB connect | `config/db.php` credentials don't match your actual MySQL setup |
| Login page loads but login fails | Database not imported yet, or imported to a differently-named database than `udm_radar` |
