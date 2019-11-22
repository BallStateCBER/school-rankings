<?php
/**
 * @var string $title_for_layout
 */

use Cake\Core\Configure;

$adminEmail = Configure::read('adminEmail');
?>

<h1>
    <?= $title_for_layout ?>
</h1>

<section class="terms">
    <h2>
        Data Disclaimer
    </h2>
    <p>
        All the information on this website (https://IndianaSchoolRankings.com/) is published in good faith and for general
        information purpose only. Indiana School Rankings does not make any warranties about the completeness, reliability
        and accuracy of this information. Any action you take upon the information you find on this website (Indiana School
        Rankings), is strictly at your own risk. Indiana School Rankings will not be liable for any losses and/or damages in
        connection with the use of our website.
    </p>
</section>

<section class="terms">
    <h2>
        Links
    </h2>
    <p>
        You can visit other websites by following this site's hyperlinks to external sites. While we strive to provide
        up-to-date and helpful links, we have no control over the content, currency, or nature of these sites. The content
        or addresses of these websites may change without notice and there may be a delay before "bad" links on this website
        are changed or removed.
    </p>
</section>

<section class="terms">
    <h2>
        Consent
    </h2>
    <p>
        By using this website, you hereby consent to the above disclaimer and agree to its terms.
    </p>
</section>

<section class="terms">
    <h2>
        Updates and Questions
    </h2>
    <p>
        Should we update, amend, or make any changes to this document, those changes will be prominently posted here.
        If you require any more information or have any questions about our site's terms of use, please feel free to
        contact us by email at <a href="mailto:<?= $adminEmail ?>"><?= $adminEmail ?></a>.
    </p>
</section>
