<?php
$resource_key   = 'solekammer';
$resources      = sor_booking_get_resources();
$definition     = $resources[ $resource_key ];
$price          = isset( $definition['price'] ) ? floatval( $definition['price'] ) : 0.00;
$requires_slot  = true;
?>
<section class="sor-form-card">
    <form
        class="sor-form"
        data-resource="<?php echo esc_attr( $resource_key ); ?>"
        data-price="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>"
        data-paypal-client="<?php echo esc_attr( sor_booking_get_paypal_client_id() ); ?>"
    >
        <?php wp_nonce_field( 'sor_booking_form', 'sor_booking_nonce' ); ?>
        <header class="sor-form__header">
            <h2><?php echo esc_html( $definition['label'] ); ?></h2>
            <p><?php echo esc_html( $definition['description'] ); ?></p>
        </header>

        <div class="sor-form__field">
            <label>
                <?php esc_html_e( 'Full Name', 'sor-booking' ); ?>
                <input type="text" name="name" required>
            </label>
        </div>

        <div class="sor-form__field">
            <label>
                <?php esc_html_e( 'Phone', 'sor-booking' ); ?>
                <input type="tel" name="phone">
            </label>
        </div>

        <div class="sor-form__field">
            <label>
                <?php esc_html_e( 'Email', 'sor-booking' ); ?>
                <input type="email" name="email" required>
            </label>
        </div>

        <div class="sor-form__field">
            <label>
                <?php esc_html_e( 'Horse name (optional)', 'sor-booking' ); ?>
                <input type="text" name="horse_name">
            </label>
        </div>

        <div class="sor-form__field">
            <label>
                <?php esc_html_e( 'Date & Time', 'sor-booking' ); ?>
                <input type="datetime-local" name="slot_start" <?php echo $requires_slot ? 'required' : ''; ?>>
            </label>
        </div>

        <input type="hidden" name="resource" value="<?php echo esc_attr( $resource_key ); ?>">
        <input type="hidden" name="price" value="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>">

        <div class="sor-form__actions">
            <button type="submit" class="sor-form__submit">
                <?php esc_html_e( 'Jetzt buchen', 'sor-booking' ); ?>
                <?php if ( $price > 0 ) : ?>
                    – <?php echo esc_html( number_format_i18n( $price, 2 ) ); ?> €
                <?php endif; ?>
            </button>
        </div>

        <div class="sor-form__feedback" aria-live="polite"></div>
        <div class="sor-form__paypal" id="paypal-button-container" hidden></div>
    </form>
</section>
