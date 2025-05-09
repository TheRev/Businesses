<?php
if ( ! current_user_can('manage_options') ) return;
$regions = get_terms(['taxonomy' => 'region', 'hide_empty' => false]);

// Determine if a nextPageToken exists for the current search context
$current_query = isset($_POST['swi_query']) ? sanitize_text_field($_POST['swi_query']) : '';
$current_radius = isset($_POST['swi_radius']) ? intval($_POST['swi_radius']) : 6000;
$search_context = md5($current_query . '|' . $current_radius);
$token_option_name = 'swi_next_page_token_' . $search_context;
$next_page_token_exists = get_option($token_option_name, false);
?>
<div class="wrap">
    <h2>Import Businesses from Google Places</h2>
    <form id="swi-import-form" method="post">
        <table class="form-table">
            <tr>
                <th><label for="swi_query">Search Query</label></th>
                <td><input type="text" id="swi_query" name="swi_query" value="<?php echo esc_attr($current_query); ?>" placeholder="e.g. Scuba Diving Shops Cozumel" size="40" required /></td>
            </tr>
            
            <tr>
                <th><label for="swi_limit">Number to Import</label></th>
                <td>
                    <select id="swi_limit" name="swi_limit">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="15">15</option>
                        <option value="20">20</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="swi_region">Region (Optional)</label></th>
                <td>
                    <select id="swi_region" name="swi_region">
                        <option value="">-- None --</option>
                        <?php foreach($regions as $region): ?>
                            <option value="<?php echo esc_attr($region->name); ?>"><?php echo esc_html($region->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <br>
                    <small>Leave blank to skip adding a region.</small>
                </td>
            </tr>
            <tr>
                <th><label for="swi_destination_manual">Destination (Optional)</label></th>
                <td>
                    <input type="text" id="swi_destination_manual" name="swi_destination_manual" value="" placeholder="Enter destination or leave blank for automatic" />
                    <br>
                    <label>
                        <input type="checkbox" id="swi_destination_auto" name="swi_destination_auto" value="1" checked>
                        Set destination automatically from Google Places address
                    </label>
                </td>
            </tr>
        </table>
        <input type="hidden" id="swi_use_next_page_token" name="swi_use_next_page_token" value="0" />
        <input type="submit" class="button button-primary" value="Import First Batch" id="swi_import_btn" />
        <button type="button" class="button" id="swi_next_batch_btn" style="display:none;">Import Next Batch</button>
        <button type="button" class="button" id="swi_reset_btn">Reset Import Progress</button>
    </form>
    <div id="swi-import-results" style="margin-top:20px;"></div>
</div>
<script>
jQuery(document).ready(function($) {
    function updateNextBatchBtn(show) {
        if (show) {
            $('#swi_next_batch_btn').show();
            $('#swi_import_btn').hide();
        } else {
            $('#swi_next_batch_btn').hide();
            $('#swi_import_btn').show();
        }
    }
    // Show next batch button if a token exists
    <?php if ($next_page_token_exists): ?>
        updateNextBatchBtn(true);
    <?php else: ?>
        updateNextBatchBtn(false);
    <?php endif; ?>

    $('#swi-import-form').on('submit', function(e) {
        e.preventDefault();
        $('#swi_use_next_page_token').val('0');
        var data = $(this).serialize();
        $('#swi-import-results').html('<div class="notice notice-info"><p>Importing...</p></div>');
        $.post(ajaxurl, data + '&action=swi_import_businesses', function(response) {
            $('#swi-import-results').html(response);
            if (response.indexOf('Next Page Token saved') !== -1) {
                updateNextBatchBtn(true);
            } else {
                updateNextBatchBtn(false);
            }
            setTimeout(function() {
                $('#swi-import-results .notice-success').fadeOut();
            }, 4000);
        }).fail(function(xhr) {
            $('#swi-import-results').html('<div class="notice notice-error is-dismissible"><p>Error: ' + xhr.responseText + '</p></div>');
        });
    });

    $('#swi_next_batch_btn').on('click', function() {
        $('#swi_use_next_page_token').val('1');
        $('#swi_import-form').submit();
    });

    $('#swi_reset_btn').on('click', function() {
        if (!confirm('Are you sure you want to reset import progress for this query/radius?')) return;
        var query = $('#swi_query').val();
        var radius = $('#swi_radius').val();
        $.post(ajaxurl, {
            action: 'swi_reset_import_progress',
            swi_query: query,
            swi_radius: radius
        }, function(response) {
            $('#swi-import-results').html('<div class="notice notice-success is-dismissible"><p>' + response + '</p></div>');
            updateNextBatchBtn(false);
        });
    });
});
</script>
