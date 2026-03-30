var ApiAccount = function ($) {
    'use strict';

    var me = {
        storage: {
            actualId: null
        },
        selector: {
            listTable: '#api_account_list',
            editPopup: '#apiAccountPopup'
        },
        escapeHtml: function (value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        },
        updateLiveTable: function () {
            $('#api_account_list').DataTable().ajax.reload();
        },
        renderV3ScopeGroups: function (scopeGroups, selectedScopes) {
            var html = '';
            selectedScopes = selectedScopes || [];
            $.each(scopeGroups || {}, function (group, scopes) {
                html += '<div class="api-v3-scope-group">';
                html += '<div class="api-v3-scope-group-title">' + me.escapeHtml(group) + '</div>';
                $.each(scopes, function (_, scopeDefinition) {
                    var checked = selectedScopes.indexOf(scopeDefinition.scope) > -1 ? ' checked' : '';
                    html += '<label class="v3-scope-row">';
                    html += '<input class="v3-scope-checkbox" type="checkbox" value="' + me.escapeHtml(scopeDefinition.scope) + '"' + checked + '>';
                    html += '<span class="api-v3-scope-copy">';
                    html += '<strong>' + me.escapeHtml(scopeDefinition.scope) + '</strong>';
                    html += '<span class="api-v3-scope-label">' + me.escapeHtml(scopeDefinition.label) + '</span>';
                    if (scopeDefinition.description) {
                        html += '<span class="api-v3-scope-description">' + me.escapeHtml(scopeDefinition.description) + '</span>';
                    }
                    html += '</span>';
                    html += '</label>';
                });
                html += '</div>';
            });
            $('#api-v3-scope-groups').html(html);
        },
        renderV3Tokens: function (tokens) {
            var html = '';
            if (!tokens || tokens.length === 0) {
                $('#api-v3-token-list').html('<div class="api-v3-empty-state">Keine v3 Tokens vorhanden.</div>');
                return;
            }
            $.each(tokens, function (_, token) {
                html += '<div class="api-v3-token-row">';
                html += '<div class="api-v3-token-heading"><strong>' + me.escapeHtml(token.label) + '</strong> <span class="api-v3-token-prefix">(' + me.escapeHtml(token.token_prefix) + '...)</span></div>';
                html += '<div class="api-v3-token-meta"><span class="api-v3-token-meta-label">Scopes:</span> ' + me.escapeHtml((token.scopes || []).join(', ')) + '</div>';
                html += '<div class="api-v3-token-meta"><span class="api-v3-token-meta-label">Erstellt:</span> ' + me.escapeHtml(token.created_at || '-') + '</div>';
                html += '<div class="api-v3-token-meta"><span class="api-v3-token-meta-label">Letzte Nutzung:</span> ' + me.escapeHtml(token.last_used_at || '-') + '</div>';
                html += '<div class="api-v3-token-meta"><span class="api-v3-token-meta-label">Läuft ab:</span> ' + me.escapeHtml(token.expires_at || '-') + '</div>';
                html += '<div class="api-v3-token-meta"><span class="api-v3-token-meta-label">Status:</span> ' + (token.revoked_at ? 'widerrufen (' + me.escapeHtml(token.revoked_at) + ')' : 'aktiv') + '</div>';
                if (!token.revoked_at) {
                    html += '<div class="api-v3-token-actions"><a href="#" class="revoke-v3-token" data-token-id="' + me.escapeHtml(token.id) + '">Token widerrufen</a></div>';
                }
                html += '</div>';
            });
            $('#api-v3-token-list').html(html);
        },
        open: function (id) {
            me.storage.actualId = id;

            $.ajax({
                url: 'index.php?module=api_account&action=list&cmd=get',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: me.storage.actualId
                },
                success: function (data) {
                    $('.permission-checkbox').prop('checked', false);
                    $('#api-account-id').text(data.id);
                    $('#aktiv').prop('checked', data.aktiv === '1');
                    $('#bezeichnung').val(data.bezeichnung);
                    $('#projekt').val(data.projekt);
                    $('#remotedomain').val(data.remotedomain);
                    $('#initkey').val(data.initkey);
                    $('#apitempkey').html(data.apitempkey);
                    $('#event_url').val(data.event_url);
                    $('#importwarteschlange_name').val(data.importwarteschlange_name);
                    $('#importwarteschlange').prop('checked', data.importwarteschlange === '1');
                    $('#cleanutf8').prop('checked', data.cleanutf8==='1');
                    $('#ishtmltransformation').prop('checked', data.ishtmltransformation === '1');
                    $('#api-v3-token-label').val('');
                    $('#api-v3-token-expires-at').val('');
                    $('#api-v3-token-secret').val('');
                    $('#api-v3-token-secret-row').hide();
                    $('#api-v3-token-create').prop('disabled', !data.id);
                    me.renderV3ScopeGroups(data.v3_scope_groups || {}, []);
                    me.renderV3Tokens(data.v3_tokens || []);
                    $('#apiAccountPopup').dialog('open');
                    var permissions = [];
                    if (data.permissions) {
                        permissions = JSON.parse(data.permissions);
                    }
                    $('.permission-checkbox').each(function () {
                        var self = $(this)
                        if(permissions.indexOf(self.attr('name')) > -1){
                            self.prop('checked', true)
                        }
                    })
                },
                error: function (e) {
                    if (typeof e.responseJSON !== 'undefined' && typeof e.responseJSON.error !== 'undefined') {
                        alert(e.responseJSON.error);
                    }
                },
                beforeSend: function () {

                }
            });
        },
        init: function () {
            if ($(me.selector.editPopup).length === 0) {
                return;
            }
            $('#api-v3-token-create').on('click', function (event) {
                event.preventDefault();
                if (!me.storage.actualId) {
                    alert('Bitte speichern Sie zuerst den API Account.');
                    return;
                }

                var scopes = [];
                $('.v3-scope-checkbox:checked').each(function () {
                    scopes.push($(this).val());
                });
                if (scopes.length === 0) {
                    alert('Bitte mindestens einen v3 Scope auswählen.');
                    return;
                }

                $.ajax({
                    url: 'index.php?module=api_account&action=list&cmd=create-v3-token',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: me.storage.actualId,
                        token_label: $('#api-v3-token-label').val(),
                        token_expires_at: $('#api-v3-token-expires-at').val(),
                        'scopes[]': scopes
                    },
                    success: function (data) {
                        $('#api-v3-token-secret').val(data.token);
                        $('#api-v3-token-secret-row').show();
                        me.renderV3Tokens(data.tokens || []);
                    },
                    error: function (e) {
                        if (typeof e.responseJSON !== 'undefined' && typeof e.responseJSON.error !== 'undefined') {
                            alert(e.responseJSON.error);
                        }
                    }
                });
            });
            $(me.selector.editPopup).on('click', '.revoke-v3-token', function (event) {
                event.preventDefault();
                $.ajax({
                    url: 'index.php?module=api_account&action=list&cmd=revoke-v3-token',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: me.storage.actualId,
                        token_id: $(this).data('token-id')
                    },
                    success: function (data) {
                        me.renderV3Tokens(data.tokens || []);
                    },
                    error: function (e) {
                        if (typeof e.responseJSON !== 'undefined' && typeof e.responseJSON.error !== 'undefined') {
                            alert(e.responseJSON.error);
                        }
                    }
                });
            });
            $('#submenu-wrapper div.new a').on('click', function () {
                me.open(0);
            });
            $(me.selector.listTable).on('afterreload', function () {
                $('#api_account_list .get').on('click', function () {
                    me.open($(this).data('id'));
                });
            });
            $(me.selector.listTable).trigger('afterreload');
            $(me.selector.editPopup).dialog(
                {
                    modal: true,
                    autoOpen: false,
                    minWidth: 940,
                    title: '',
                    buttons: {
                        'SPEICHERN': function () {
                            var permissions = {};
                            $('.permission-checkbox').each(function () {
                                var self = $(this)
                                permissions[self.attr('name')] = self.is(':checked')
                            })

                            $.ajax({
                                url: 'index.php?module=api_account&action=list&cmd=save',
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    id: me.storage.actualId,
                                    aktiv: $('#aktiv').prop('checked') ? 1 : 0,
                                    bezeichnung: $('#bezeichnung').val(),
                                    projekt: $('#projekt').val(),
                                    remotedomain: $('#remotedomain').val(),
                                    initkey: $('#initkey').val(),
                                    event_url: $('#event_url').val(),
                                    importwarteschlange_name: $('#importwarteschlange_name').val(),
                                    importwarteschlange: $('#importwarteschlange').prop('checked') ? 1 : 0,
                                    cleanutf8: $('#cleanutf8').prop('checked') ? 1 : 0,
                                    ishtmltransformation: $('#ishtmltransformation').prop('checked') ? 1 : 0,
                                    api_permissions: permissions,

                                },
                                success: function () {
                                    me.storage.actualId = null;
                                    me.updateLiveTable();
                                    $(me.selector.editPopup).dialog('close');
                                },
                                error: function (e) {
                                    if (typeof e.responseJSON !== 'undefined' && typeof e.responseJSON.error !==
                                        'undefined') {
                                        alert(e.responseJSON.error);
                                    }
                                },
                                beforeSend: function () {

                                }
                            });
                        },
                        'ABBRECHEN': function () {
                            $(this).dialog('close');
                            me.storage.actualId = null;
                        }
                    },
                    close: function (event, ui) {

                    }
                });
            $(me.selector.editPopup).toggleClass('hidden', false);
        }
    };
    return {
        init: me.init
    };
}(jQuery);

$(document).ready(function () {
    ApiAccount.init();
});
