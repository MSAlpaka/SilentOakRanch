<?php
$resource    = 'schmied';
$resources   = sor_booking_get_resources();
$definition  = $resources[ $resource ];
?>
<form class="sor-booking-form" data-resource="<?php echo esc_attr( $resource ); ?>">
    <h2><?php echo esc_html( $definition['label'] ); ?></h2>
    <p><?php echo esc_html( $definition['description'] ); ?></p>

    <label>
        <?php esc_html_e( 'Your Name', 'sor-booking' ); ?>
        <input type="text" name="name" required />
    </label>

    <label>
        <?php esc_html_e( 'Phone', 'sor-booking' ); ?>
        <input type="tel" name="phone" required />
    </label>

    <label>
        <?php esc_html_e( 'Email', 'sor-booking' ); ?>
        <input type="email" name="email" required />
    </label>

    <label>
        <?php esc_html_e( 'Horse Name', 'sor-booking' ); ?>
        <input type="text" name="horse_name" />
    </label>

    <button type="submit"><?php esc_html_e( 'Send request', 'sor-booking' ); ?></button>

    <div class="sor-booking-result"></div>
    <div class="sor-booking-paypal"></div>
    <div class="sor-booking-qr-container"></div>
</form>
