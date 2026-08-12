# GitHub Actions Sandbox-Deployment (OpenXE)

Diese Anleitung richtet das automatische Update der OpenXE-Sandbox bei jedem Push auf `master` ein.

Die Pipeline liegt in `.github/workflows/deploy-sandbox.yml` und verbindet sich per SSH mit dem Sandbox-Server. Dort wird `upgrade.php -do -f` als `www-data` ausgefuehrt (Code + Datenbank in einem Lauf).

## Zielserver

```text
Host: 46.224.70.197
User: jakob
Repo: /var/www/html/OpenXE
```

OpenXE muss auf dem Server bereits installiert sein. Git-Operationen laufen als `www-data` (Besitzer von `.git/`). Der Server muss aus `upgrade/data/remote.json` bzw. per HTTPS aus GitHub pullen koennen.

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
SANDBOX_SSH_HOST=46.224.70.197
SANDBOX_SSH_PORT=22
SANDBOX_SSH_USER=jakob
SANDBOX_SSH_PRIVATE_KEY=<Inhalt des privaten SSH-Keys>
SANDBOX_SSH_KNOWN_HOSTS=<gepruefter Host-Key des Servers>
SANDBOX_DEPLOY_PATH=/var/www/html/OpenXE
```

### Private Key einfuegen

Auf deinem Windows-Rechner:

```powershell
Get-Content "C:\Users\Jakob\.ssh\OpenXE\openxe" -Raw
```

Den kompletten Inhalt inklusive `-----BEGIN OPENSSH PRIVATE KEY-----` ... `-----END OPENSSH PRIVATE KEY-----` als Wert von `SANDBOX_SSH_PRIVATE_KEY` einfuegen.

### Known Hosts sicher setzen

```bash
ssh-keyscan -H 46.224.70.197
ssh-keygen -lf <(ssh-keyscan 46.224.70.197 2>/dev/null)
```

Die `ssh-keyscan -H`-Ausgabe als `SANDBOX_SSH_KNOWN_HOSTS` speichern. Falls `ssh-keyscan` lokal scheitert, auf dem Server:

```bash
sudo cat /etc/ssh/ssh_host_ed25519_key.pub
```

Beispiel fuer das Secret:

```text
46.224.70.197 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA...
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

GitHub Actions kann kein interaktives sudo-Passwort eingeben. Der Deploy-User braucht passwortloses sudo fuer `php` als `www-data`:

```bash
sudo visudo -f /etc/sudoers.d/openxe-deploy
```

Inhalt:

```sudoers
jakob ALL=(www-data) NOPASSWD: /usr/bin/php /var/www/html/OpenXE/upgrade/data/upgrade.php *
```

Danach pruefen:

```bash
sudo -n -u www-data php /var/www/html/OpenXE/upgrade/data/upgrade.php -db
```

Wenn ein Passwort abgefragt wird, schlaegt auch die GitHub Action fehl.

## Was die Pipeline macht

Bei jedem Push auf `master`:

1. SSH-Verbindung zur Sandbox mit Secrets und geprueftem Host-Key.
2. Aktuellen Commit merken (`PREVIOUS_REV`).
3. `sudo -u www-data php data/upgrade.php -do -f` im Verzeichnis `upgrade/`:
   - Code von GitHub pullen (`-do` ohne `-db`/`-s` = Code **und** DB)
   - geaenderte `gitinfo.json` per `-f` akzeptieren
4. Pruefen, dass `git rev-parse HEAD` dem Pipeline-Commit (`github.sha`) entspricht.
5. HTTP-Healthcheck gegen `http://127.0.0.1/OpenXE/`.
6. Bei Fehler: Code-Rollback auf `PREVIOUS_REV` (kein DB-Rollback).

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
| `sudo: a password is required` | sudoers-Regel fehlt | `/etc/sudoers.d/openxe-deploy` pruefen |
| `Clear modified files or use -f` | lokale Aenderungen blockieren Upgrade | Pipeline nutzt `-f`; manuell ggf. `git checkout -- gitinfo.json` |
| `Unerwarteter Commit nach Deploy` | Pull hat anderen Stand als `master`-HEAD | `remote.json` und GitHub-Remote pruefen |
| Upgrade nur DB, kein Code | manuell `-do -db` statt `-do` | Pipeline nutzt korrekt `-do -f` |

## Sicherheitshinweise

- Private SSH-Keys nur in GitHub Secrets, nie ins Repo.
- `StrictHostKeyChecking=yes` in der Pipeline.
- Die Action hat nur `contents: read`.
- Sandbox-Deploy laeuft ueber `environment: sandbox`.
