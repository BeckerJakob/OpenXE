<!-- gehort zu tabview -->
<div id="tabs">
    <ul>
        <li><a href="#tabs-1"><!--[TABTEXT]--></a></li>
    </ul>

    <div id="tabs-1">
    [MESSAGE]

    <div id="apiV3Hub" class="api-v3-hub">
        <div class="api-v3-hub-card api-v3-hub-intro">
            <h2 class="api-v3-hub-title">API v3 (REST)</h2>
            <p class="api-v3-hub-lead">
                Connectoren authentifizieren sich mit einem <strong>Bearer-Token</strong> (<code>Authorization: Bearer …</code>).
                Das Secret wird nur einmal nach dem Erstellen angezeigt.
            </p>
            <p class="api-v3-hub-meta">
                Endpunkt-Basis (je nach Installation): <code>/www/api/v3/index.php</code>
                &mdash; siehe OpenAPI unter <code>www/api/v3/docs/openapi.yaml</code>.
            </p>
        </div>

        <div class="api-v3-hub-card">
            <label class="api-v3-hub-label" for="api-v3-hub-account-select">API Account</label>
            <select id="api-v3-hub-account-select" class="api-v3-hub-select">
                <option value="">-- Bitte w&auml;hlen --</option>
            </select>
            <p class="api-v3-hub-hint">
                Tokens sind an einen bestehenden API Account gebunden.
                <a href="index.php?module=api_account&amp;action=list">Zur &Uuml;bersicht / neuen Account anlegen</a>
            </p>
        </div>

        <div id="api-v3-hub-token-panel" class="api-v3-hub-card api-v3-hub-token-panel" style="display:none;">
            <div class="api-v3-hub-field">
                <label for="api-v3-hub-token-label">Label</label>
                <input type="text" id="api-v3-hub-token-label" class="api-v3-hub-input" placeholder="z. B. Shop Connector">
            </div>

            <div class="api-v3-hub-field">
                <label for="api-v3-hub-token-expires-at">L&auml;uft ab</label>
                <input type="text" id="api-v3-hub-token-expires-at" class="api-v3-hub-input" placeholder="optional, z. B. 2026-12-31 23:59:59">
            </div>

            <div class="api-v3-hub-field">
                <div class="api-v3-hub-scope-header">
                    <span class="api-v3-hub-scope-heading">Scopes</span>
                    <div class="api-v3-hub-scope-bulk-actions">
                        <button type="button" id="api-v3-hub-scopes-select-all" class="api-v3-hub-btn api-v3-hub-btn-compact">Alle ausw&auml;hlen</button>
                        <button type="button" id="api-v3-hub-scopes-select-none" class="api-v3-hub-btn api-v3-hub-btn-compact">Alle abw&auml;hlen</button>
                    </div>
                </div>
                <div id="api-v3-hub-scope-groups" class="api-v3-hub-scope-groups"></div>
            </div>

            <div class="api-v3-hub-actions">
                <button type="button" id="api-v3-hub-token-create" class="api-v3-hub-btn api-v3-hub-btn-primary">v3 Token erstellen</button>
            </div>

            <div id="api-v3-hub-token-secret-row" class="api-v3-hub-field" style="display:none;">
                <label for="api-v3-hub-token-secret">Neues Secret</label>
                <div class="api-v3-hub-secret-row">
                    <input type="text" id="api-v3-hub-token-secret" class="api-v3-hub-input api-v3-hub-secret" readonly>
                    <button type="button" id="api-v3-hub-copy-secret" class="api-v3-hub-btn">Kopieren</button>
                </div>
            </div>

            <div class="api-v3-hub-field">
                <label>Vorhandene Tokens</label>
                <div id="api-v3-hub-token-list" class="api-v3-hub-token-list"></div>
            </div>
        </div>
    </div>

    </div>
</div>

<style>
#apiV3Hub.api-v3-hub {
    max-width: 920px;
    margin: 16px 0 24px;
    font-size: 14px;
    line-height: 1.45;
}
.api-v3-hub-card {
    background: var(--surface-bg, #fafafa);
    border: 1px solid var(--border-color, #ddd);
    border-radius: 8px;
    padding: 16px 18px;
    margin-bottom: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.api-v3-hub-title {
    margin: 0 0 8px;
    font-size: 1.25rem;
    font-weight: 600;
}
.api-v3-hub-lead {
    margin: 0 0 8px;
}
.api-v3-hub-meta {
    margin: 0;
    color: #555;
    font-size: 13px;
}
.api-v3-hub-meta code {
    font-size: 12px;
}
.api-v3-hub-label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}
.api-v3-hub-select,
.api-v3-hub-input {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
.api-v3-hub-hint {
    margin: 10px 0 0;
    font-size: 13px;
    color: #555;
}
.api-v3-hub-field {
    margin-top: 14px;
}
.api-v3-hub-field:first-child {
    margin-top: 0;
}
.api-v3-hub-field > label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}
.api-v3-hub-scope-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
.api-v3-hub-scope-heading {
    font-weight: 600;
}
.api-v3-hub-scope-bulk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.api-v3-hub-btn-compact {
    padding: 5px 10px;
    font-size: 13px;
}
.api-v3-hub-scope-groups {
    max-height: 320px;
    overflow: auto;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 4px;
    padding: 10px 12px;
}
.api-v3-hub-scope-group + .api-v3-hub-scope-group {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #eee;
}
.api-v3-hub-scope-group-title {
    font-weight: 600;
    margin-bottom: 6px;
}
.api-v3-hub-scope-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 8px;
}
.api-v3-hub-scope-row input {
    margin-top: 3px;
}
.api-v3-hub-scope-description {
    display: block;
    color: #666;
    font-size: 12px;
    margin-top: 2px;
}
.api-v3-hub-actions {
    margin-top: 16px;
}
.api-v3-hub-btn {
    padding: 8px 14px;
    border-radius: 4px;
    border: 1px solid #bbb;
    background: #fff;
    cursor: pointer;
}
.api-v3-hub-btn-primary {
    background: var(--primary-color, #1976d2);
    color: #fff;
    border-color: transparent;
}
.api-v3-hub-btn-primary:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.api-v3-hub-secret-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.api-v3-hub-secret {
    flex: 1;
    min-width: 200px;
    font-family: ui-monospace, monospace;
    font-size: 13px;
}
.api-v3-hub-token-list {
    margin-top: 8px;
}
.api-v3-hub-token-row {
    padding: 12px 0;
    border-top: 1px solid #eee;
}
.api-v3-hub-token-row:first-child {
    border-top: 0;
    padding-top: 0;
}
.api-v3-hub-token-prefix {
    color: #666;
}
.api-v3-hub-token-meta {
    margin-top: 4px;
    font-size: 13px;
}
.api-v3-hub-empty {
    color: #666;
}
.api-v3-hub-revoke {
    color: #b00020;
}
</style>
