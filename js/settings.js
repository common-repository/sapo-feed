jQuery(document).ready(function($) {
    var nutsContainer = $( '#sapo_rss_default_nuts_container' );
    var nutsValue = $( '#sapo_rss_default_nuts' );

    // Setup

    $('<label>').text('Distrito / Região').appendTo(nutsContainer);
    var districtSelect = $('<select>').attr('style','display:block;margin:.5em 0;width:175px;').appendTo(nutsContainer);
    $('<label>').text('Concelho').appendTo(nutsContainer);
    var municipalitySelect = $('<select>').attr('style','display:block;margin:.5em 0;width:175px;').appendTo(nutsContainer);

    // Helpers

    districtSelect.change(function() {
        municipalitySelect.empty();

        var district = nutsJSON.list.filter(function(d) { return d.value == districtSelect.val() }).pop();

        $(district.municipalities).each(function() {
            var options = {
                text: this.label,
                value: this.value,
            };
            if (String(nutsValue.val()) == this.value) options.selected = 1;

            municipalitySelect.append( $("<option>", options) );
        });
        municipalitySelect.change();
    });

    municipalitySelect.change(function() {
        nutsValue.val( municipalitySelect.val() );
    });

    // Start

    $(nutsJSON.list).each(function() {
        var options = {
            text: this.label,
            value: this.value,
        };

        var nutsPrefix = String(nutsValue.val()).substr(0,2);

        if (nutsPrefix.startsWith('3')) nutsPrefix = '30';  // Madeira
        if (nutsPrefix.startsWith('4')) nutsPrefix = '40';  // Açores
        if (nutsPrefix == this.value) options.selected = 1;
        if (this.disabled) options.disabled = 1;

        districtSelect.append( $("<option>", options) );
    });
    districtSelect.change();
});
