<?php
/**
 * Persistent, unmissable development-data warning. Visible on every page
 * while seed_mode = development, so demonstration rows cannot be mistaken
 * for production evidence.
 */
if (!is_test_mode()) {
    return;
}
?>
<div class="test-banner" role="status">
    <span class="test-banner__tag">TEST DATA</span>
    <span>
        This installation is in development seed mode. Records created by the seed script are clearly marked
        <strong>TEST DATA</strong> and must not be treated as real institutional results. Disable seed mode and use a clean
        production database before live use.
    </span>
</div>
