var ApiAccountV3 = function ($) {
    'use strict';

    var baseUrl = 'index.php?module=api_account&action=list';

    var me = {
        storage: {
            accountId: null
        },
        escapeHtml: function (value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        },
        getQueryAccountId: function () {
            var match = /[?&]account=(\d+)/.exec(window.location.search);
            return match ? parseInt(match[1], 10) : 0;
        },
        renderScopeGroups: function (scopeGroups, selectedScopes) {
            var html = '';
            selectedScopes = selectedScopes || [];
            $.each(scopeGroups || {}, function (group, scopes) {
                html += '<div class="api-v3-hub-scope-group">';
                html += '<div class="api-v3-hub-scope-group-title">' + me.escapeHtml(group) + '</div>';
                $.each(scopes, function (_, scopeDefinition) {
                    var checked = selectedScopes.indexOf(scopeDefinition.scope) > -1 ? ' checked' : '';
                    html += '<label class="api-v3-hub-scope-row">';
                    html += '<input class="api-v3-hub-scope-checkbox" type="checkbox" value="' + me.escapeHtml(scopeDefinition.scope) + '"' + checked + '>';
                    html += '<span>';
                    html += '<strong>' + me.escapeHtml(scopeDefinition.scope) + '</strong>';
                    html += '<span class="api-v3-hub-scope-description">' + me.escapeHtml(scopeDefinition.label) + '</span>';
                    if (scopeDefinition.description) {
                        html += '<span class="api-v3-hub-scope-description">' + me.escapeHtml(scopeDefinition.description) + '</span>';
                    }
                    html += '</span>';
                    html += '</label>';
                });
                html += '</div>';
            });
            $('#api-v3-hub-scope-groups').html(html);
        },
        renderTokens: function (tokens) {
            var html = '';
            if (!tokens || tokens.length === 0) {
                $('#api-v3-hub-token-list').html('<div class="api-v3-hub-empty">Keine v3 Tokens vorhanden.</div>');
                return;
            }
            $.each(tokens, function (_, token) {
                html += '<div class="api-v3-hub-token-row">';
                html += '<div><strong>' + me.escapeHtml(token.label) + '</strong> <span class="api-v3-hub-token-prefix">(' + me.escapeHtml(token.token_prefix) + '...)</span></div>';
                html += '<div class="api-v3-hub-token-meta"><strong>Scopes:</strong> ' + me.escapeHtml((token.scopes || []).join(', ')) + '</div>';
                html += '<div class="api-v3-hub-token-meta"><strong>Erstellt:</strong> ' + me.escapeHtml(token.created_at || '-') + '</div>';
                html += '<div class="api-v3-hub-token-meta"><strong>Letzte Nutzung:</strong> ' + me.escapeHtml(token.last_used_at || '-') + '</div>';
                html += '<div class="api-v3-hub-token-meta"><strong>L&auml;uft ab:</strong> ' + me.escapeHtml(token.expires_at || '-') + '</div>';
                html += '<div class="api-v3-hub-token-meta"><strong>Status:</strong> ' + (token.revoked_at ? 'widerrufen (' + me.escapeHtml(token.revoked_at) + ')' : 'aktiv') + '</div>';
                if (!token.revoked_at) {
                    html += '<div class="api-v3-hub-token-meta"><a href="#" class="api-v3-hub-revoke revoke-v3-hub-token" data-token-id="' + me.escapeHtml(token.id) + '">Token widerrufen</a></div>';
                }
                html += '</div>';
            });
            $('#api-v3-hub-token-list').html(html);
        },
        loadAccounts: function () {
            return $.ajax({
                url: baseUrl + '&cmd=list-accounts',
                type: 'GET',
                dataType: 'json'
            });
        },
        loadAccountDetail: function (id) {
            return $.ajax({
                url: baseUrl + '&cmd=get',
                type: 'POST',
                dataType: 'json',
                data: { id: id }
            });
        },
        applyAccountData: function (data) {
            me.renderScopeGroups(data.v3_scope_groups || {}, []);
            me.renderTokens(data.v3_tokens || []);
            $('#api-v3-hub-token-label').val('');
            $('#api-v3-hub-token-expires-at').val('');
            $('#api-v3-hub-token-secret').val('');
            $('#api-v3-hub-token-secret-row').hide();
        },
        selectAccount: function (id) {
            var numericId = parseInt(id, 10) || 0;
            me.storage.accountId = numericId;
            if (numericId <= 0) {
                $('#api-v3-hub-token-panel').hide();
                return;
            }
            $('#api-v3-hub-token-panel').show();
            me.loadAccountDetail(numericId).done(function (data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                me.applyAccountData(data);
            }).fail(function (e) {
                if (e.responseJSON && e.responseJSON.error) {
                    alert(e.responseJSON.error);
                }
            });
        },
        init: function () {
            if ($('#apiV3Hub').length === 0) {
                return;
            }

            me.loadAccounts().done(function (rows) {
                var $sel = $('#api-v3-hub-account-select');
                $sel.find('option:not(:first)').remove();
                if (!rows || !rows.length) {
                    return;
                }
                $.each(rows, function (_, row) {
                    var id = row.id;
                    var label = me.escapeHtml(row.bezeichnung || ('#' + id));
                    var inactive = row.aktiv === '0' || row.aktiv === 0 ? ' (inaktiv)' : '';
                    $sel.append('<option value="' + me.escapeHtml(String(id)) + '">' + label + inactive + '</option>');
                });
                var pre = me.getQueryAccountId();
                if (pre > 0) {
                    $sel.val(String(pre));
                    me.selectAccount(pre);
                }
            }).fail(function (e) {
                if (e.responseJSON && e.responseJSON.error) {
                    alert(e.responseJSON.error);
                }
            });

            $('#api-v3-hub-account-select').on('change', function () {
                me.selectAccount($(this).val());
            });

            $('#api-v3-hub-scopes-select-all').on('click', function (event) {
                event.preventDefault();
                $('.api-v3-hub-scope-checkbox').prop('checked', true);
            });
            $('#api-v3-hub-scopes-select-none').on('click', function (event) {
                event.preventDefault();
                $('.api-v3-hub-scope-checkbox').prop('checked', false);
            });

            $('#api-v3-hub-token-create').on('click', function (event) {
                event.preventDefault();
                var id = me.storage.accountId;
                if (!id) {
                    alert('Bitte einen API Account ausw&auml;hlen.');
                    return;
                }
                var scopes = [];
                $('.api-v3-hub-scope-checkbox:checked').each(function () {
                    scopes.push($(this).val());
                });
                if (scopes.length === 0) {
                    alert('Bitte mindestens einen v3 Scope ausw&auml;hlen.');
                    return;
                }
                $.ajax({
                    url: baseUrl + '&cmd=create-v3-token',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: id,
                        token_label: $('#api-v3-hub-token-label').val(),
                        token_expires_at: $('#api-v3-hub-token-expires-at').val(),
                        'scopes[]': scopes
                    },
                    success: function (data) {
                        $('#api-v3-hub-token-secret').val(data.token);
                        $('#api-v3-hub-token-secret-row').show();
                        me.renderTokens(data.tokens || []);
                    },
                    error: function (e) {
                        if (e.responseJSON && e.responseJSON.error) {
                            alert(e.responseJSON.error);
                        }
                    }
                });
            });

            $('#api-v3-hub-token-list').on('click', '.revoke-v3-hub-token', function (event) {
                event.preventDefault();
                var tokenId = $(this).data('token-id');
                $.ajax({
                    url: baseUrl + '&cmd=revoke-v3-token',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: me.storage.accountId,
                        token_id: tokenId
                    },
                    success: function (data) {
                        me.renderTokens(data.tokens || []);
                    },
                    error: function (e) {
                        if (e.responseJSON && e.responseJSON.error) {
                            alert(e.responseJSON.error);
                        }
                    }
                });
            });

            $('#api-v3-hub-copy-secret').on('click', function () {
                var text = $('#api-v3-hub-token-secret').val();
                if (!text) {
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).catch(function () {
                        window.prompt('Token kopieren:', text);
                    });
                } else {
                    window.prompt('Token kopieren:', text);
                }
            });
        }
    };

    return {
        init: me.init
    };
}(jQuery);

$(document).ready(function () {
    ApiAccountV3.init();
});
