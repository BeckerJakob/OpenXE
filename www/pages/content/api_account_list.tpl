<!-- gehort zu tabview -->
<div id="tabs">
    <ul>
        <li><a href="#tabs-1"><!--[TABTEXT]--></a></li>
    </ul>
    <!-- ende gehort zu tabview -->

    <!-- erstes tab -->
    <div id="tabs-1">
    [MESSAGE]
    [TAB1]
    [TAB1NEXT]
    </div>

    <!-- tab view schließen -->
</div>

<style>
    #apiAccountPopup .api-v3-token-panel {
        border: 1px solid #d7d7d7;
        background: #fafafa;
        padding: 12px;
        border-radius: 4px;
    }

    #apiAccountPopup .api-v3-token-intro {
        margin-bottom: 12px;
        line-height: 1.4;
    }

    #apiAccountPopup .api-v3-token-field {
        margin-top: 10px;
    }

    #apiAccountPopup .api-v3-token-field label {
        display: block;
        font-weight: bold;
        margin-bottom: 4px;
    }

    #apiAccountPopup .api-v3-token-field input[type="text"] {
        width: 100%;
        box-sizing: border-box;
    }

    #apiAccountPopup .api-v3-scope-groups {
        margin-top: 10px;
        max-height: 300px;
        overflow: auto;
        border: 1px solid #d7d7d7;
        background: #fff;
        padding: 10px 12px;
    }

    #apiAccountPopup .api-v3-scope-group + .api-v3-scope-group {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #eee;
    }

    #apiAccountPopup .api-v3-scope-group-title {
        font-weight: bold;
        margin-bottom: 6px;
    }

    #apiAccountPopup .v3-scope-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 8px;
        line-height: 1.35;
    }

    #apiAccountPopup .v3-scope-row input {
        margin-top: 3px;
    }

    #apiAccountPopup .api-v3-scope-copy {
        display: block;
    }

    #apiAccountPopup .api-v3-scope-label {
        display: block;
        margin-top: 2px;
    }

    #apiAccountPopup .api-v3-scope-description {
        display: block;
        margin-top: 2px;
        color: #666;
        font-size: 12px;
    }

    #apiAccountPopup .api-v3-token-actions {
        margin-top: 12px;
    }

    #apiAccountPopup #api-v3-token-create {
        padding: 6px 12px;
        cursor: pointer;
    }

    #apiAccountPopup #api-v3-token-create:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    #apiAccountPopup #api-v3-token-secret {
        width: 100%;
        box-sizing: border-box;
        font-family: monospace;
    }

    #apiAccountPopup #api-v3-token-list {
        margin-top: 12px;
    }

    #apiAccountPopup .api-v3-token-row {
        padding: 10px 0;
        border-top: 1px solid #eee;
    }

    #apiAccountPopup .api-v3-token-row:first-child {
        border-top: 0;
        padding-top: 0;
    }

    #apiAccountPopup .api-v3-token-heading {
        margin-bottom: 6px;
    }

    #apiAccountPopup .api-v3-token-prefix {
        color: #666;
    }

    #apiAccountPopup .api-v3-token-meta {
        margin-top: 3px;
    }

    #apiAccountPopup .api-v3-token-meta-label {
        font-weight: bold;
    }

    #apiAccountPopup .api-v3-empty-state {
        color: #666;
    }

    #apiAccountPopup .api-v3-token-actions a {
        color: #b00020;
    }
</style>

<div id="apiAccountPopup" class="hidden">
    <fieldset><legend>{|API Account|}</legend>
        <table width="100%">
            <tr>
                <td><label for="api-account-id">{|API Account ID|}:</label></td>
                <td><span id="api-account-id"></span></td>
            </tr>
            <tr>
                <td><label for="aktiv">{|Aktiv|}:</label></td>
                <td><input type="checkbox" id="aktiv" name="aktiv" value="1"></td>
            </tr>
            <tr>
                <td><label for="bezeichnung">{|Bezeichnung|}:</label></td>
                <td><input type="text" id="bezeichnung" name="bezeichnung" size="40"></td><td>
            </tr>
            <tr>
                <td><label for="projekt">{|Projekt|}:</label></td>
                <td><input id="projekt" type="text" size="40" name="projekt"></td>
            </tr>
            <tr>
                <td><label for="remotedomain">{|App Name|} / {|Benutzername|}:</label></td>
                <td><input type="text" id="remotedomain" name="remotedomain" size="40"></td><td>
            </tr>
            <tr>
                <td><label for="initkey">{|Initkey|} / {|Passwort|}:</label></td>
                <td><input type="text" id="initkey" name="initkey" size="40"></td><td>
            </tr>
            <tr>
                <td>{|Aktueller Key|}:</td>
                <td><span id="apitempkey">[APITEMPKEY]</span> <i>F&uuml;r Testzwecke</i></td><td>
            </tr>
            <tr>
                <td><label for="event_url">{|Event URL|}:</label></td>
                <td><input type="text" id="event_url" name="event_url" size="40"></td><td>
            </tr>
            <tr>
                <td><label for="importwarteschlange_name">{|Warteschlangename Bezeichnung|}:</label></td>
                <td><input type="text" id="importwarteschlange_name" name="importwarteschlange_name" size="40"></td><td>
            </tr>
            <tr>
                <td><label for="importwarteschlange">{|Import Warteschlange|}:</label></td>
                <td><input type="checkbox" id="importwarteschlange" name="importwarteschlange" value="1"></td>
            </tr>
            <tr>
                <td><label for="cleanutf8">{|UTF8 Clean|}:</label></td>
                <td><input type="checkbox" id="cleanutf8" name="cleanutf8" value="1"></td>
            </tr>
            <tr>
                <td><label for="ishtmltransformation">{|Ohne HTML Umwandlung|}:</label></td>
                <td><input type="checkbox" id="ishtmltransformation" name="ishtmltransformation" value="1"></td>
            </tr>
            <tr>
                <td><span>Permissions</span></td>
            </tr>
            [API_PERMISSIONS_HTML]
            <tr>
                <td colspan="2"><hr></td>
            </tr>
            <tr>
                <td valign="top"><strong>API v3 Token</strong></td>
                <td>
                    <div class="api-v3-token-panel">
                        <div class="api-v3-token-intro">
                            Bearer Tokens f&uuml;r die neue API v3. Das Secret wird nur direkt nach dem Erstellen angezeigt.
                        </div>

                        <div class="api-v3-token-field">
                            <label for="api-v3-token-label">Label</label>
                            <input type="text" id="api-v3-token-label" placeholder="z. B. Shop Connector">
                        </div>

                        <div class="api-v3-token-field">
                            <label for="api-v3-token-expires-at">L&auml;uft ab</label>
                            <input type="text" id="api-v3-token-expires-at" placeholder="optional, z. B. 2026-12-31 23:59:59">
                        </div>

                        <div class="api-v3-token-field">
                            <label>Scopes</label>
                            <div id="api-v3-scope-groups" class="api-v3-scope-groups"></div>
                        </div>

                        <div class="api-v3-token-actions">
                            <button type="button" id="api-v3-token-create">v3 Token erstellen</button>
                        </div>

                        <div id="api-v3-token-secret-row" class="api-v3-token-field" style="display:none;">
                            <label for="api-v3-token-secret">Neues Secret</label>
                            <input type="text" id="api-v3-token-secret" readonly>
                        </div>

                        <div class="api-v3-token-field">
                            <label>Vorhandene Tokens</label>
                            <div id="api-v3-token-list"></div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </fieldset>
</div>