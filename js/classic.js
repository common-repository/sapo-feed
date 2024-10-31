jQuery(document).ready(function($) {
    var metaInput          = $( '#sapo_feed_post_nuts' );
    var addSelectorButton  = $( '#sapo-rss-classic-add-geo' );
    var sidebarContent     = $( '#sapo-rss-classic-sidebar-content' );

    var meta = (metaInput.prop('value') || '');

    var state = {
        maxAllowed: 10,
        nuts: ((meta == '') ? [] : meta.split(','))
    };

    var isMaxReached = function( className ) {
        return ( $(className).length >= state.maxAlloweds );
    };

    var updateMetaInput =  function(name) {
        state.nuts = [];
        $( '.sapo-rss-classic-municipality' ).each(function() {
            state.nuts.push( $(this).val() );
        });

        metaInput.prop('value', state.nuts.join(','));
    };

    var createSelector = function ( parent, nutsValue ) {
        var module = $('<div>');

        var districtDiv     = $('<div>', { class: 'sapo-rss-classic-control' });
        var municipalityDiv = $('<div>', { class: 'sapo-rss-classic-control' });

        var districtSelect     = $('<select>', { class: 'sapo-rss-classic-district' });
        var municipalitySelect = $('<select>', { class: 'sapo-rss-classic-municipality' });

        districtDiv.append( '<label>Distrito / Região</label>' );
        municipalityDiv.append( '<label>Concelho</label>' );

        // municipality select setup

        municipalitySelect.change(function() {
            updateMetaInput( 'municipalitySelect.change' );
        });

        // district select setup
        districtSelect.change(function() {
            municipalitySelect.empty();

            var district = nutsJSON.list.filter(function(d) { return d.value == districtSelect.val() }).pop();

            $(district.municipalities).each(function() {
                var options = {
                    text: this.label,
                    value: this.value,
                };
                if (String(nutsValue) == this.value) options.selected = 1;

                municipalitySelect.append( $('<option>', options) );
            });
            municipalitySelect.change();
        });
        $(nutsJSON.list).each(function() {
            var options = {
                text: this.label,
                value: this.value,
            };

            var nutsPrefix = String(nutsValue).substr(0,2);

            if (nutsPrefix.startsWith('3')) nutsPrefix = '30';  // Madeira
            if (nutsPrefix.startsWith('4')) nutsPrefix = '40';  // Açores
            if (nutsPrefix == this.value) options.selected = 1;
            if (this.disabled) options.disabled = 1;

            districtSelect.append( $('<option>', options) );
        });
        districtSelect.change();

        // finish up
        districtDiv.append( districtSelect );
        municipalityDiv.append( municipalitySelect );

        module.append( districtDiv );
        module.append( municipalityDiv );

        var removeLink = $('<a>', {
            text:  'Remover',
            href:  '#',
        });

        removeLink.click(function(ev) {
            ev.preventDefault();
            module.remove();
            updateMetaInput( 'removeLink.click' );
            addSelectorButton.prop('disabled', isMaxReached( '.sapo-rss-classic-municipality' ));
        });

        module.append( $('<div>', { class: 'sapo-rss-classic-remove' }).append( removeLink ) );
        parent.append( module );

        addSelectorButton.prop('disabled', isMaxReached( '.sapo-rss-classic-municipality' ));
        updateMetaInput( 'createSelector' );
    };

    if ( addSelectorButton && sidebarContent ) {
        // Helpers
        addSelectorButton.click(function(ev) {
            ev.preventDefault();
            createSelector( sidebarContent, nutsJSON.option );
        });

        // Start
        state.nuts.forEach(function (nutsValue) {
            createSelector( sidebarContent, nutsValue );
        })
    }
});