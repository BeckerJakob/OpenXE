# GitHub Actions Sandbox-Deployment (OpenXE)

Diese Anleitung richtet das automatische Update der OpenXE-Sandbox bei jedem Push auf `master` ein.

Die Pipeline liegt in `.github/workflows/deploy-sandbox.yml` und verbindet sich per SSH mit dem Sandbox-Server. Dort wird `upgrade.php -do -f` als Deploy-User ausgefuehrt (Code + Datenbank in einem Lauf).

## Zielserver

```text
Host: <SANDBOX_SSH_HOST>
User: <deploy-user>
Repo: /var/www/html/OpenXE
```

OpenXE muss auf dem Server bereits installiert sein. Fuer Git/Upgrade wird das Repo temporaer auf den Deploy-User umgestellt (`chown`), danach wieder auf `www-data`. Der Server muss aus `upgrade/data/remote.json` bzw. per HTTPS aus GitHub pullen koennen.

## GitHub Environment

In GitHub unter:

```text
Repository -> Settings -> Environments -> New environment
```

ein Environment mit dem Namen `sandbox` anlegen.

## GitHub Secrets

In GitHub unter:

```text
Repository -> Settings -> Secrets and variables -> Actions -> New repository secret
```

folgende Secrets anlegen:

```text
SANDBOX_SSH_HOST=<Sandbox-Host oder IP>
SANDBOX_SSH_PORT=22
SANDBOX_SSH_USER=<deploy-user>
SANDBOX_SSH_PRIVATE_KEY=<Inhalt des privaten SSH-Keys>
SANDBOX_SSH_KNOWN_HOSTS=<gepruefter Host-Key des Servers>
SANDBOX_DEPLOY_PATH=/var/www/html/OpenXE
SANDBOX_HTTP_HOST=<optional: ServerName/Domain fuer OpenXE im Browser>
```

`SANDBOX_HTTP_HOST` ist optional, aber empfohlen (z. B. `openxe-sbx.example.com` aus `apache2ctl -S`). Die Pipeline prueft mehrere HTTPS-Pfade (`/OpenXE/www/index.php`, `favicon.ico`, …) und akzeptiert 200/30x/401. Bei nur 403 oder ohne erreichbaren Endpunkt: Fallback auf `apache2`-Status und App-Dateien.

### Private Key einfuegen

Lokal den privaten Deploy-Key auslesen, z. B.:

```powershell
Get-Content "~/.ssh/your-deploy-key" -Raw
```

Den kompletten Inhalt inklusive `-----BEGIN OPENSSH PRIVATE KEY-----` ... `-----END OPENSSH PRIVATE KEY-----` als Wert von `SANDBOX_SSH_PRIVATE_KEY` einfuegen.

### Known Hosts sicher setzen

```bash
ssh-keyscan -H SANDBOX_HOST
ssh-keygen -lf <(ssh-keyscan SANDBOX_HOST 2>/dev/null)
```

Die `ssh-keyscan -H`-Ausgabe als `SANDBOX_SSH_KNOWN_HOSTS` speichern. Falls `ssh-keyscan` lokal scheitert, auf dem Server:

```bash
sudo cat /etc/ssh/ssh_host_ed25519_key.pub
```

Beispiel fuer das Secret:

```text
SANDBOX_HOST ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA...
```

## Migration von der alten Pipeline

Die fruehere Einzel-Pipeline `deploy-openxe-upgrade.yml` nutzte generische Secrets:

```text
OPENXE_SSH_HOST
OPENXE_SSH_USER
OPENXE_SSH_PRIVATE_KEY
OPENXE_SSH_KNOWN_HOSTS
OPENXE_DEPLOY_PATH
```

Checkliste:

1. Neue Secrets `SANDBOX_SSH_*` und `SANDBOX_DEPLOY_PATH` anlegen (Werte aus den alten Secrets uebernehmen, wenn die alte Pipeline die Sandbox bedient hat).
2. Environment `sandbox` anlegen.
3. Einen Testlauf ueber `Actions -> Deploy OpenXE Sandbox -> Run workflow` starten.
4. Nach erfolgreichem Lauf die alten `OPENXE_SSH_*`-Secrets loeschen.

## Sudo ohne Passwort fuer Deployment

GitHub Actions kann kein interaktives sudo-Passwort eingeben. Der Deploy-User braucht passwortloses `chown`, weil das Repo fuer Git/Upgrade temporaer auf den Deploy-User umgestellt wird (wie beim Automation Hub):

```bash
sudo visudo -f /etc/sudoers.d/openxe-deploy
```

Inhalt (`<deploy-user>` durch den SSH-User ersetzen):

```sudoers
<deploy-user> ALL=(root) NOPASSWD: /usr/bin/chown
```

`upgrade.php` muss aus dem Verzeichnis `upgrade/` mit relativem Pfad `data/upgrade.php` gestartet werden und laeuft als Deploy-User (nicht als `www-data`).

Dateirechte der sudoers-Datei setzen (sonst Warnung von `visudo -c`):

```bash
sudo chown root:root /etc/sudoers.d/openxe-deploy
sudo chmod 0440 /etc/sudoers.d/openxe-deploy
sudo visudo -c
```

Danach pruefen (exakt wie in der GitHub Action):

```bash
sudo chown -R <deploy-user>:<deploy-user> /var/www/html/OpenXE
cd /var/www/html/OpenXE/upgrade
php data/upgrade.php -db
git -C /var/www/html/OpenXE status -sb
sudo chown -R www-data:www-data /var/www/html/OpenXE
```

Wenn ein Passwort abgefragt wird, schlaegt auch die GitHub Action fehl.

## Was die Pipeline macht

Bei jedem Push auf `master`:

1. SSH-Verbindung zur Sandbox mit Secrets und geprueftem Host-Key.
2. Aktuellen Commit merken (`PREVIOUS_REV`).
3. Repo temporaer auf Deploy-User umstellen, dann `php data/upgrade.php -do -f` im Verzeichnis `upgrade/`:
   - Code von GitHub pullen (`-do` ohne `-db`/`-s` = Code **und** DB)
   - geaenderte `gitinfo.json` per `-f` akzeptieren
4. Repo wieder auf `www-data` zurueckstellen.
5. Pruefen, dass `git rev-parse HEAD` dem Pipeline-Commit (`github.sha`) entspricht.
6. HTTP-Healthcheck: mit `SANDBOX_HTTP_HOST` per `Host`-Header, sonst Fallback (Apache aktiv + `index.php` vorhanden).
7. Bei Fehler: Code-Rollback auf `PREVIOUS_REV` (kein DB-Rollback).

## Manuell ausloesen

```text
GitHub -> Actions -> Deploy OpenXE Sandbox -> Run workflow
```

## Fehlerdiagnose

Auf GitHub den fehlgeschlagenen Step oeffnen. Auf dem Server:

```bash
cd /var/www/html/OpenXE
git log -1 --oneline
git status -sb
tail -n 80 upgrade/data/upgrade.log
sudo tail -n 80 /var/log/apache2/error.log
```

Typische Ursachen:

| Fehler | Ursache | Loesung |
| --- | --- | --- |
| `Permission denied (publickey)` | falscher Secret-Key oder User | `SANDBOX_SSH_*` pruefen |
| `Host key verification failed` | `SANDBOX_SSH_KNOWN_HOSTS` fehlt/falsch | Host-Key neu setzen |
| `sudo: a password is required` | sudoers fuer `chown` fehlt | Regel `<deploy-user> ALL=(root) NOPASSWD: /usr/bin/chown` in `/etc/sudoers.d/openxe-deploy` |
| `Permission denied` auf `.git/index.lock` | Upgrade lief als `www-data`, Repo gehoert Deploy-User | Pipeline nutzt temporaeres `chown` auf Deploy-User |
| `Unerwarteter Commit nach Deploy` | Pull hat anderen Stand als `master`-HEAD | `remote.json` und GitHub-Remote pruefen |
| `curl: (22) The requested URL returned error: 403` | Statische Dateien blockiert, Login-Seite evtl. OK | Pipeline prueft `/OpenXE/www/index.php`; testen: `curl -sS -o /dev/null -w '%{http_code}\n' --resolve 'HOST:443:127.0.0.1' https://HOST/OpenXE/www/index.php` |
| Upgrade nur DB, kein Code | manuell `-do -db` statt `-do` | Pipeline nutzt korrekt `-do -f` |

## Sicherheitshinweise

- Private SSH-Keys nur in GitHub Secrets, nie ins Repo.
- `StrictHostKeyChecking=yes` in der Pipeline.
- Die Action hat nur `contents: read`.
- Sandbox-Deploy laeuft ueber `environment: sandbox`.
