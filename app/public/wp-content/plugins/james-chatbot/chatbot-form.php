<?php

function jcb_domain_form_shortcode() {

    if (!is_user_logged_in()) {
        return "<p>Please login first.</p>";
    }

    ob_start();
?>

<form method="post">
    <label>Enter Domain:</label><br>
    <input type="text" name="jcb_domain" required>
    <br><br>
    <button type="submit" name="jcb_submit_domain">
        Start Chatbot
    </button>
</form>

<?php
    return ob_get_clean();
}
