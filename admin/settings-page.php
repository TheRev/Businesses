<?php
if ( ! current_user_can('manage_options') ) return;

$api_key = esc_attr(get_option('swi_google_places_api_key'));
$saved = isset($_GET['settings-updated']) && $_GET['settings-updated'];
?>
<div class="wrap">
    <h2>Shops WP Importer Settings</h2>
    <?php if ($saved): ?>
        <div id="message" class="updated notice notice-success is-dismissible">
            <p>API key saved!</p>
        </div>
    <?php endif; ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('swi_settings_group');
        do_settings_sections('swi_settings_group');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Google Places API Key</th>
                <td>
                    <input type="text" name="swi_google_places_api_key" value="<?php echo $api_key; ?>" size="50"/>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
