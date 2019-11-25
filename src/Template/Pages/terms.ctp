<?php
/**
 * @var string $title_for_layout
 */

use Cake\Core\Configure;

$adminEmail = Configure::read('adminEmail');
?>

<section class="terms">
    <h1>
        <?= $title_for_layout ?>
    </h1>
    <p>
        Please read these Terms of Use ("Terms", "Terms of Use") carefully before using the
        https://IndianaSchoolRankings.com website (the "Service") operated by The Ball State University Center for
        Business and Economic Research ("us", "we", or "our"). Your access to and use of the Service is conditioned on
        your acceptance of and compliance with these Terms. These Terms apply to all visitors, users and others who
        access or use the Service. By accessing or using the Service you agree to be bound by these Terms. If you
        disagree with any part of the terms then you may not access the Service.
    </p>
</section>

<section class="terms">
    <h2>
        Data Disclaimer
    </h2>
    <p>
        All the information on this website is published in good faith and for general information purpose only. We do
        not make any warranties about the completeness, reliability, and accuracy of this information. Any action you
        take upon the information you find on this website, is strictly at your own risk. We will not be liable for any
        losses and/or damages in connection with the use of our website.
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
        Accounts
    </h2>
    <p>
        You are responsible for safeguarding the password that you use to access the Service and for any activities or
        actions under your password, whether your password is with our Service or a third-party service.
    </p>
    <p>
        You agree not to disclose your password to any third party. You must notify us immediately upon becoming aware
        of any breach of security or unauthorized use of your account.
    </p>
</section>

<section class="terms">
    <h2>
        Termination
    </h2>
    <p>
        We may terminate or suspend access to our Service immediately, without prior notice or liability, for any
        reason, including without limitation if you breach the Terms. Upon termination, your right to use the Service
        will immediately cease. If you wish to terminate your account, you may simply discontinue using the Service.
    </p>
    <p>
        All provisions of the Terms which by their nature should survive termination shall survive termination,
        including, without limitation, ownership provisions, warranty disclaimers, indemnity and limitations of
        liability.
    </p>
</section>

<section class="term">
    <h2>
        Governing Law
    </h2>
    <p>
        These Terms shall be governed and construed in accordance with the laws of Indiana, United States, without
        regard to its conflict of law provisions.
    </p>
    <p>
        Our failure to enforce any right or provision of these Terms will not be considered a waiver of those rights.
        If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining provisions of
        these Terms will remain in effect. These Terms constitute the entire agreement between us regarding our Service,
        and supersede and replace any prior agreements we might have between us regarding the Service.
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
