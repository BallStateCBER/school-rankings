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
    <p>
        Your privacy is very important to us. This Privacy Policy document contains types of information that is
        collected and recorded by Indiana School Rankings and how we use it.
    </p>

    <p>
        The Ball State University Center for Business and Economic Research (CBER) will retain your personal information
        only for as long as is necessary for the purposes set out in this Privacy Policy. We will retain and use your
        information to the extent necessary to comply with our legal obligations, resolve disputes, and enforce our
        policies.
    </p>
</section>

<section class="terms">
    <h2>
        Log Files
    </h2>

    <p>
        Indiana School Rankings follows a standard procedure of using log files. These files log visitors when they
        visit websites, as well as errors and other conditions that are indicative of the website experiencing a
        problem. The information collected by log files can include internet protocol (IP) addresses, browser type,
        Internet Service Provider (ISP), date and time stamp, and referring/exit pages and are used to identify
        problems, to analyze the traffic patterns of visitors navigating to the website and within the website, and to
        collect anonymous demographic information. These are not linked to any information that is personally
        identifiable.
    </p>
</section>

<section class="terms">
    <h2>
        Activity Monitoring
    </h2>

    <p>
        In addition to anonymized logs of visitor information described above, Indiana School Rankings retains
        information about visitor activity, including information submitted by forms, for the purpose of
        researching how the public uses this website. This information is not used to identify any individual person's
        activity, but rather aggregated together to describe general trends in the scope of all users. In collecting
        this information, we may use cookies and logged-in user sessions to distinguish your activity from that of other
        visitors, and if you are logged in, your email address may be linked to records of your activity. We may
        publish this anonymized information publicly, transfer it to another Data Controller, distribute it to
        third-parties, or sell it to third-parties. Unless if it is to fulfill a legal requirement, we will in no case
        knowingly allow any of your Personally Identifiable Information to be accessed by a third party.
    </p>
</section>

<section class="terms">
    <h2>
        Cookies
    </h2>

    <p>
        Like any other website, Indiana School Rankings uses "cookies". These cookies are used to store information
        including visitors' preferences and the pages on the website that the visitor accessed or visited. The
        information is used to optimize the users' experience by customizing our web page content based the visitors'
        needs and preferences.
    </p>

    <p>
        You can choose to disable or delete cookies through your individual browser options. For more detailed
        information about cookie management with specific web browsers, visit those browsers' respective websites.
    </p>
</section>

<section class="terms">
    <h2>
        Third Party Privacy Policies
    </h2>

    <p>
        Indiana School Rankings links to external websites, but its Privacy Policy does not apply to those websites.
        Thus, we are advising you to consult the respective Privacy Policies of these third-party websites for more
        detailed information. It may include their practices and instructions about how to opt-out of certain options.
    </p>
</section>

<section class="terms">
    <h2>
        Children's Information
    </h2>

    <p>
        Protection for children while using the internet is important to us. We encourage parents and guardians to
        observe, participate in and/or monitor, and guide children's online activity.
    </p>

    <p>
        Indiana School Rankings does not knowingly collect any Personally Identifiable Information from children under the
        age of 13. If you think that your child provided this kind of information on our website, we strongly encourage
        you to contact us immediately and we will do our best efforts to promptly remove such information from our
        records.
    </p>
</section>

<section class="terms">
    <h2>
        Online Privacy Policy Only
    </h2>

    <p>
        Our Privacy Policy applies only to our online activities and is valid for visitors
        to our website with regards to the information that they shared and/or which we collect from them in Indiana
        School Rankings. This policy is not applicable to any information collected offline or via channels other than
        this website.
    </p>
</section>

<section class="terms">
    <h2>
        Consent
    </h2>

    <p>
        By using our website, you hereby consent to our Privacy Policy and agree to its terms.
    </p>
</section>

<section class="terms">
    <h2>
        Questions
    </h2>
    <p>
        If you have additional questions or require more information about our Privacy Policy, do not hesitate to contact
        us by email at <a href="mailto:<?= $adminEmail ?>"><?= $adminEmail ?></a>.
    </p>
</section>
