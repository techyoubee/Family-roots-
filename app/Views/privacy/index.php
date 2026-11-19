<?php ?>
<section class="section">
    <div class="wrap prose">
        <p class="eyebrow">Privacy</p>
        <h1>Privacy policy - plain language version</h1>
        <p class="text-muted">Last updated <?= e(date('d M Y')) ?>. This policy describes how this installation of Family Roots handles information. Adjust and review it before using the platform in production.</p>

        <h2>What we collect</h2>
        <ul>
            <li><strong>Account data:</strong> your name, email address and a securely hashed password. Nothing more is required to use the service.</li>
            <li><strong>Ancestry data you choose to enter:</strong> names, relationships, approximate dates, places, notes and the sources you cite.</li>
            <li><strong>Security data:</strong> sign-in attempts, IP address and browser user agent, recorded so abusive activity can be investigated.</li>
        </ul>
        <p>We do not collect identity documents, DNA data, biometric data or precise location data, and we do not require any of them.</p>

        <h2>What we never do</h2>
        <ul>
            <li>We never publish ancestor records in the public directory; they are visible only to that family's members and to platform administrators for oversight.</li>
            <li>We never share one family's records with another family without an approval decision.</li>
            <li>We never place evidence files inside the public web directory; sources you record are stored as text in the database.</li>
            <li>We never write passwords into logs. With the log mail transport used on local installations, confirmation and reset links are written to a local log file instead of being emailed.</li>
        </ul>

        <h2>Who can see what</h2>
        <ul>
            <li><strong>Public:</strong> the platform pages, plus family profiles that a family has chosen to list in the directory. Aggregate counts only - never ancestor records.</li>
            <li><strong>Family members:</strong> the trees, relationships and notes of the families they belong to.</li>
            <li><strong>Family administrators:</strong> applications, cited sources and audit history for their own family only.</li>
            <li><strong>Platform administrators:</strong> family verification, abuse handling and system health. Regenerating a confirmation link is recorded in the audit log.</li>
        </ul>

        <h2>Your rights and controls</h2>
        <ul>
            <li><strong>Access and correction:</strong> view and update your account details, and ask a family administrator to correct a family record you cannot edit yourself.</li>
            <li><strong>Deletion:</strong> request account deletion from your settings. The account is locked immediately and a platform administrator processes the request.</li>
            <li><strong>Consent:</strong> information about minors must only be entered with the consent of a parent or guardian.</li>
        </ul>

        <h2>Retention</h2>
        <p>Audit records for approvals and verification decisions are retained so decisions remain explainable. Security logs are pruned periodically.</p>

        <h2>Contact</h2>
        <p class="mb-0">Questions or requests about your data can be sent to the platform administrators for this installation.</p>
    </div>
</section>
