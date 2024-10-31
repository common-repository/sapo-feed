(function(wp) {
    var el = wp.element.createElement;

    var Fragment = wp.element.Fragment;
    var Button = wp.components.Button;
    var SelectControl = wp.components.SelectControl;

    var withSelect = wp.data.withSelect;
    var withDispatch = wp.data.withDispatch;

    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var registerPlugin = wp.plugins.registerPlugin;

    var mapMetaToState = function(select) {
        var meta = (select('core/editor').getEditedPostAttribute('meta')['sapo_feed_post_nuts'] || '');
        var state = {
            maxAllowed: 10,
            nuts: ((meta == '') ? [] : meta.split(',')),
            selections: [],
        };

        state.getMeta = function() {
            return state.nuts.join(',');
        }

        state.updateSelections = function() {
            return state.nuts.forEach(function (n, i) {
                var nutsPrefix = String(n).substr(0,2);
                if (nutsPrefix.startsWith('3')) nutsPrefix = '30'; // Madeira
                if (nutsPrefix.startsWith('4')) nutsPrefix = '40'; // Açores

                state.selections.push({
                    position: i,
                    selectedDistrict: nutsPrefix,
                    selectedMunicipality: String(n)
                });
            });
        }
        state.updateSelections();

        state.addNUTS = function() {
            if (state.nuts.length >= state.maxAllowed) {
                return;
            }

            var newNUTS = nutsJSON.option || '000000';
            state.nuts.push(newNUTS);
            state.updateSelections();
        };

        state.removeNUTS = function(position) {
            state.nuts.splice(position, 1);
            state.updateSelections();
        };

        state.setNUTS = function(value, position) {
            state.nuts[position] = value;
            state.updateSelections();
        };

        return state;
    }

    var mapDispatchToState = function(dispatch) {
        return {
            setMetaFieldValue: function(value) {
                dispatch('core/editor').editPost(
                    { meta: { sapo_feed_post_nuts: value } }
                );
            }
        }
    }

    var GeoSelector = function (state) {
        var selectors = [];

        (function () {
            state.selections.forEach(function (selection) {
                selectors.push(
                    el(
                        Fragment,
                        {},
                        el(SelectControl, {
                            label: "Distrito / Região",
                            value: selection.selectedDistrict,
                            options: nutsJSON.list,
                            onChange: function(value) {
                                var district = (value == 0) ? '000000' : value + '0000';

                                state.setNUTS(district, selection.position);
                                state.setMetaFieldValue(state.getMeta());
                            }
                        }),
                        el(SelectControl, {
                            label: "Concelho",
                            value: selection.selectedMunicipality,
                            options: (function () {
                                var district = nutsJSON.list.filter(function(el) {
                                    return el.value === selection.selectedDistrict;
                                });
                                if (Array.isArray(district) && (district.length > 0)) {
                                    return district[0].municipalities
                                }
                                return [{value: "000000", label: "Nenhum", slug: "no-municipality"}];
                            })(),
                            onChange: function(value) {
                                state.setNUTS(value, selection.position);
                                state.setMetaFieldValue(state.getMeta());
                            }
                        }),
                        el('div', { className: 'sapo-rss-geoselector-control' },
                            el(Button, {
                                text: 'Remover',
                                isLink: true,
                                onClick: function() {
                                    state.removeNUTS(selection.position);
                                    state.setMetaFieldValue(state.getMeta());
                                },
                            })
                        ),
                        el('hr')
                    )
                )
            });
        })()

        return selectors;
    }

    var Geo = function(state) {
        var addDisabled = (state.nuts.length >= state.maxAllowed) ? true : false;

        return el(
            Fragment,
            {},
            el(
                PluginSidebarMoreMenuItem,
                {
                    target: 'sapo-rss-sidebar',
                },
                'SAPO Feed'
            ),
            el(
                PluginSidebar,
                {
                    name: 'sapo-rss-sidebar',
                    title: 'SAPO Feed',
                },
                el('div',
                    { className: 'sapo-rss-sidebar-content' },
                    el('h4', {}, el('span', {}, "Informação Geográfica")),
                    el('p', { className: 'components-form-token-field__help' }, "Associar dados de localização (opcional)."),
                    el('div', { className: 'sapo-rss-geoselector-control' },
                        el(Button, {
                            disabled: addDisabled,
                            text: 'Adicionar',
                            isSecondary: true,
                            iconPosition: 'right',
                            onClick: function() {
                                state.addNUTS();
                                state.setMetaFieldValue(state.getMeta());
                            },
                        })
                    ),
                    el(GeoSelector, state)
                ),
            )
        );
    }

    var GeoWithData = withSelect(mapMetaToState)(Geo);
    var GeoWithDataAndActions = withDispatch(mapDispatchToState)(GeoWithData);

    registerPlugin('sapo-rss-sidebar', {
        icon: 'sapo-logo',
        render: GeoWithDataAndActions,
    });
})(window.wp);