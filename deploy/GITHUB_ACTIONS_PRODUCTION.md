# GitHub Actions Produktiv-Deployment (OpenXE)

Diese Anleitung richtet ein manuell freizugebendes OpenXE-Deployment auf den Produktivserver ein.

Die Pipeline liegt in `.github/workflows/deploy-production.yml`. Sie verbindet sich per SSH mit dem Produktivserver und fuehrt dort `upgrade.php -do -f` als Deploy-User aus (Code + Datenbank).

## Sicherheitsmodell

Das Produktiv-Deployment laeuft bewusst nicht automatisch bei jedem Push.

- Start nur manuell ueber `workflow_dispatch`.
- Start nur vom Branch `master`.
- Der Workflow verlangt die Eingabe `deploy-production`.
- Der Job nutzt `environment: production`.
- In GitHub sollte fuer das Environment `production` eine manuelle Freigabe mit Required Reviewers gesetzt werden.
- Die Action hat nur `contents: read`.
- SSH nutzt einen privaten Key aus GitHub Secrets und `StrictHostKeyChecking=yes`.
- Bei Fehlern nach dem Git-Update versucht die Pipeline ein Rollback des **Codes** auf den vorherigen Commit. **Kein DB-Rollback.**

## Voraussetzungen auf dem Produktivserver

```text
Host: <PRODUCTION_SSH_HOST> (z. B. SSH-Alias openxe-prd)
User: <deploy-user>
Repo: /var/www/html/OpenXE
```

OpenXE muss installiert sein. Wichtige Konfiguration bleibt auf dem Server und wird vom Upgrade nicht ueberschrieben:

```text
/var/www/html/OpenXE/conf/user.inc.php
/var/www/html/OpenXE/userdata/
```

Vor jedem Produktiv-Deployment ein **Datenbank-Backup** erstellen (z. B. `mysqldump openxe`).

## GitHub Environment

In GitHub unter:

```text
Repository -> Settings -> Environments -> New environment
```

ein Environment mit dem Namen `production` anlegen.

Empfohlen:

- `Required reviewers` aktivieren.
- `Deployment branches and tags` auf `Selected branches` begrenzen.
- Nur `master` erlauben.

## GitHub Secrets

```text
PRODUCTION_SSH_HOST=<Produktivserver-Host oder IP>
PRODUCTION_SSH_PORT=22
PRODUCTION_SSH_USER=<deploy-user>
PRODUCTION_SSH_PRIVATE_KEY=<Inhalt des privaten SSH-Keys>
PRODUCTION_SSH_KNOWN_HOSTS=<gepruefter Host-Key des Produktivservers>
PRODUCTION_DEPLOY_PATH=/var/www/html/OpenXE
PRODUCTION_HTTP_HOST=<optional: ServerName/Domain fuer OpenXE im Browser>
```

`PRODUCTION_HTTP_HOST` ist optional, aber empfohlen (ServerName aus `apache2ctl -S`; HTTPS-Check per `--resolve`).

Der private Key darf nicht ins Repository committed werden.

### Known Hosts sicher setzen

```bash
ssh-keyscan -H PRODUKTIV_HOST
ssh-keygen -lf <(ssh-keyscan PRODUKTIV_HOST 2>/dev/null)
```

Alternativ auf dem Server:

```bash
sudo cat /etc/ssh/ssh_host_ed25519_key.pub
```

## Migration von der alten Pipeline

Die fruehere `deploy-openxe-upgrade.yml` nutzte `OPENXE_SSH_*` und `OPENXE_DEPLOY_PATH` fuer ein einzelnes Ziel.

Checkliste fuer den Wechsel auf Sandbox + Produktion:

1. Secrets `PRODUCTION_SSH_*` und `PRODUCTION_DEPLOY_PATH` anlegen (Host/IP des Produktivservers, nicht Sandbox).
2. Environment `production` mit Required Reviewers anlegen.
3. Sandbox zuerst testen (`deploy/GITHUB_ACTIONS_SANDBOX.md`).
4. Produktiv-Deployment manuell mit `deploy-production` ausloesen.
5. Nach erfolgreichem Produktiv-Lauf alte `OPENXE_SSH_*`-Secrets entfernen.
6. Sicherstellen, dass Push auf `master` **nur** die Sandbox-Pipeline startet, nicht Produktion.

## Sudo ohne Passwort fuer Deployment

```bash
sudo visudo -f /etc/sudoers.d/openxe-deploy
```

Inhalt (`<deploy-user>` durch den SSH-User ersetzen):

```sudoers
<deploy-user> ALL=(root) NOPASSWD: /usr/bin/chown
```

`upgrade.php` laeuft als Deploy-User aus dem Verzeichnis `upgrade/` mit relativem Pfad `data/upgrade.php`.

Dateirechte der sudoers-Datei setzen:

```bash
sudo chown root:root /etc/sudoers.d/openxe-deploy
sudo chmod 0440 /etc/sudoers.d/openxe-deploy
sudo visudo -c
```

Pruefen (exakt wie in der GitHub Action):

```bash
sudo chown -R <deploy-user>:<deploy-user> /var/www/html/OpenXE
cd /var/www/html/OpenXE/upgrade
php data/upgrade.php -db
git -C /var/www/html/OpenXE status -sb
sudo chown -R www-data:www-data /var/www/html/OpenXE
```

## Was die Pipeline macht

1. Manuelle Bestaetigung und `master`-Branch pruefen.
2. SSH-Verbindung mit Secrets und geprueftem Host-Key.
3. Aktuellen Commit merken (`PREVIOUS_REV`).
4. Repo temporaer auf Deploy-User umstellen, dann `php data/upgrade.php -do -f` (Code + DB gegen neues Schema).
5. Repo wieder auf `www-data` zurueckstellen.
6. Pruefen, dass `git rev-parse HEAD` dem Pipeline-Commit entspricht.
7. HTTP-Healthcheck: mit `PRODUCTION_HTTP_HOST` per `Host`-Header, sonst Fallback (Apache aktiv + `index.php` vorhanden).
8. Bei Fehler nach Git-Update: `git reset --hard` auf `PREVIOUS_REV` (nur Code).

## Manuell ausloesen

```text
GitHub -> Actions -> Deploy OpenXE Production -> Run workflow
```

Wichtig:

- Branch `master` auswaehlen.
- In `confirm` exakt `deploy-production` eintragen.
- Falls Environment-Schutzregeln gesetzt sind, Deployment freigeben.
- Vorher Datenbank-Backup erstellen.

## Fehlerdiagnose

```bash
cd /var/www/html/OpenXE
git log -1 --oneline
git status -sb
tail -n 120 upgrade/data/upgrade.log
sudo tail -n 120 /var/log/apache2/error.log
```

Haeufige Ursachen:

| Fehler | Ursache | Loesung |
| --- | --- | --- |
| `Permission denied (publickey)` | falscher Key/User | `PRODUCTION_SSH_*` pruefen |
| `Host key verification failed` | Known Hosts fehlen | `PRODUCTION_SSH_KNOWN_HOSTS` setzen |
| `sudo: a password is required` | sudoers fuer `chown` fehlt | Regel `<deploy-user> ALL=(root) NOPASSWD: /usr/bin/chown` in `/etc/sudoers.d/openxe-deploy` |
| `Permission denied` auf `.git/index.lock` | Upgrade lief als `www-data`, Repo gehoert Deploy-User | Pipeline nutzt temporaeres `chown` auf Deploy-User |
| `Clear modified files or use -f` | `gitinfo.json` geaendert | Pipeline nutzt `-f`; bei manuellem Lauf ebenfalls `-f` |
| `curl: (22) The requested URL returned error: 403` | Statische Dateien blockiert | `PRODUCTION_HTTP_HOST` setzen; `/OpenXE/www/index.php` per `--resolve` testen |
| Schema-Differenzen nach Upgrade | PRD-DB weicht vom JSON ab | oft normal; `upgrade.php -db` pruefen, Backup vor Prod |
| Code zurueck, DB vorn | nur Code-Rollback bei Fehler | DB manuell aus Backup wiederherstellen falls noetig |

## Rollback-Grenzen

- **Code:** Die Pipeline setzt bei Fehlern den vorherigen Git-Commit zurueck.
- **Datenbank:** Kein automatischer Rollback. Vor Produktiv-Deployments immer Backup.
- **Konfiguration:** `conf/user.inc.php` und `userdata/` werden vom Standard-Upgrade nicht ueberschrieben; trotzdem Backup empfohlen.

## Sicherheitshinweise

- Produktiv-Deployments nie automatisch bei Push.
- Required Reviewers auf Environment `production` setzen.
- Private Keys nur in GitHub Secrets.
- Nach Migration alte `OPENXE_SSH_*`-Secrets entfernen, um Verwechslungen zu vermeiden.
