<?php
/*
Plugin Name: Chalmers Card Widget
Description: Shows account balance for Chalmers Student Union Cards.
Author: Johan Winther (johwin)
Text Domain: chcw
Domain Path: /languages
*/

/*
For fetching card account balance. Uses the api at https://ftek.se/api/card-balance/v1/
*/

add_action( 'init', 'init_chcw' );
function init_chcw() {
    // Load translations
    load_plugin_textdomain('chcw', false, basename( dirname( __FILE__ ) ) . '/languages' );

    if ( !class_exists( 'Defuse\Crypto\Crypto' ) ) {
        require_once( 'vendor/autoload.php'); // Make sure to run composer install in current folder to download dependencies
    }
}

function chcw_get_balance($cardNumber) {
    // Fetch and decode JSON file
    $url = "https://ftek.se/api/card-balance/v1/" . $cardNumber;
    $response = wp_remote_get( $url, array( 'timeout' => 2) );
    if ( is_wp_error( $request ) ) {
        return false;
    }
    $result = $response['body'];

    $cardObject = json_decode($result);
    return $cardObject;
}

// Register and load the widget
function chalmers_card_load_widget() {
    register_widget( 'ChalmersCardWidget' );
}
add_action( 'widgets_init', 'chalmers_card_load_widget' );

// Creating the widget
class ChalmersCardWidget extends WP_Widget {

    function __construct() {
        parent::__construct(

            // Base ID of your widget
            'chalmers_card_widget',

            // Widget name will appear in UI
            __('Chalmers Card Widget', 'chcw'),

            // Widget description
            array( 'description' => __( 'Shows account balance for Chalmers Student Union Cards.', 'chcw' ), )
        );
    }

    // Creating widget front-end

    public function widget( $args, $instance ) {
        $key = Defuse\Crypto\Key::loadFromAsciiSafeString( CHALMERS_ENCRYPT_KEY );
        $cardNumber = "";
        $cardNumberEncrypted = get_user_meta(get_current_user_id(), 'chalmers-card', true);
        if ($cardNumberEncrypted != "")
            $cardNumber = Defuse\Crypto\Crypto::decrypt($cardNumberEncrypted, $key );
        
        if ($cardNumber != "") {
            $cardObject = chcw_get_balance($cardNumber);

            // before and after widget arguments are defined by themes
            echo $args['before_widget'];
            echo $args['before_title'] . __( 'Card Balance', 'chcw' ) . $args['after_title'];

            // This is where you run the code and display the output

            // If connection timed out
            if (!$cardObject) {
                echo 'Could not connect to card server.';
            } else if (isset($cardObject->error)) {
                echo $cardObject->error;
            } else {
                echo '<span id="name">' . $cardObject->cardHolder . '</span><br>';
                echo '<span id="balance">' . number_format_i18n($cardObject->cardBalance->value, 2). ' ' . __('SEK', 'chcw') . '</span>';
            }
            echo $args['after_widget'];
        }
    }

    // Widget Backend
    public function form( $instance ) {

    }

    // Updating widget replacing old instances with new
    public function update( $new_instance, $old_instance ) {

    }
} // Class chalmers_card_widget ends here


/*
* Profile field for Student Union card
*/
add_action( 'show_user_profile', 'user_meta_show_form_field_chalmers_card' );

function user_meta_show_form_field_chalmers_card( $user ) {

    $key = Defuse\Crypto\Key::loadFromAsciiSafeString( CHALMERS_ENCRYPT_KEY );

    ?>

    <h3>Chalmers</h3>

    <table class="form-table">
        <tr>
            <th>
                <label for="chalmers_card"><?= __('Student Union Card' , 'chcw') ?></label>
            </th>
            <td>
                <input type="number"
                class="regular-text ltr"
                id="chalmers-card"
                name="chalmers-card"
                value="<?= esc_attr(Defuse\Crypto\Crypto::decrypt(get_user_meta($user->ID, 'chalmers-card' , true)), $key); ?>"
                title="<?= __("You can find your 16 digit number on your Student Union Card.", 'chcw') ?>"
                pattern="\d{16}"
                required>
                <p class="description">
                    <?= __("Write the whole number on your Student Union Card. This needs to be updated when you get a new one.",'chcw') ?>
                </p>
            </td>
        </tr>
    </table>
<?php }

add_action( 'personal_options_update', 'user_meta_update_form_field_chalmers_card' );

/**
* The save action.
*
* @param $user_id int the ID of the current user.
*
* @return bool Meta ID if the key didn't exist, true on successful update, false on failure.
*/
function user_meta_update_form_field_chalmers_card( $user_id ) {

    // check that the current user have the capability to edit the $user_id
    if (!current_user_can('edit_user', $user_id) || ($_POST['chalmers-card'] != "" && !preg_match('/\d{16}/', $_POST['chalmers-card']))) {
        return false;
    }

    // create/update user meta for the $user_id but encrypt it first
    $key = Defuse\Crypto\Key::loadFromAsciiSafeString( CHALMERS_ENCRYPT_KEY );
    $cardNumberEncrypted = Defuse\Crypto\Crypto::encrypt($_POST['chalmers-card'], $key);
    //$cardNumberEncrypted = $_POST['chalmers-card'];
    return update_user_meta(
        $user_id,
        'chalmers-card',
        $cardNumberEncrypted
    );
}
