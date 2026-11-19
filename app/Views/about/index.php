<?php
/**
 * @var array $matching
 */
?>
<section class="section">
    <div class="wrap prose">
        <p class="eyebrow">About the platform</p>
        <h1>Family Roots exists so families can keep their own history, honestly</h1>
        <p class="lead">
            Most family history is lost because it lives in one person's memory or one dusty notebook. Family Roots
            gives families a shared, structured place to record what is known, mark clearly what is uncertain, and
            decide together who belongs - without pretending that software can prove kinship on its own.
        </p>

        <h2>Independent families, isolated data</h2>
        <p>
            Every family registers its own profile, tree, members and administrators. Family B can never see Family A's
            records, cited sources or applications unless Family A explicitly grants membership. Private family profiles do not
            even appear in the directory: the page does not reveal whether the family exists.
        </p>

        <h2 id="matching">How ancestry matching works</h2>
        <p>
            When you apply to join a family, you declare the relatives you know - parents, grandparents and older
            generations - as precisely as you can manage. The matching engine then compares each declared person with the
            people already recorded in that family's tree.
        </p>
        <p>The engine looks at several independent signals, never one alone:</p>
        <ul>
            <li><strong>Recorded relationships</strong> - does the person you name as your father have a documented place
                in the tree that is consistent with your branch?</li>
            <li><strong>Names and spelling variants</strong> - transliterated, accented and alternated spellings are
                compared, because the same person is often written several ways.</li>
            <li><strong>Dates and places</strong> - approximate years and places are used as supporting signals, never as
                sole proof.</li>
            <li><strong>Evidence</strong> - a source note recorded against a person or relationship carries more
                weight than a name coincidence.</li>
        </ul>
        <p>
            You receive an explanation for every claim: what matched, how strongly, and what a human needs to check.
            A surname, a village or a single shared ancestor is deliberately not enough.
        </p>

        <h2>Automatic approval only under strict, published rules</h2>
        <p>
            Automatic approval runs only when every configured condition is satisfied - a minimum score built from at
            least two independent signals, a documented parent and grandparent chain, no conflicting claims, and a family
            whose history has been verified. The current configuration of this installation is:
        </p>
        <ul>
            <li>Minimum match score for automatic approval:
                <strong><?= e($matching['auto_approve']['min_score'] ?? 'n/a') ?></strong></li>
            <li>Minimum independent signals required:
                <strong><?= e($matching['min_independent_signals'] ?? 'n/a') ?></strong></li>
            <li>Parent and grandparent chain required:
                <strong><?= !empty($matching['auto_approve']['require_parent_and_grandparent']) ? 'yes' : 'no' ?></strong></li>
            <li>Conflicting claims tolerated:
                <strong><?= e($matching['auto_approve']['max_conflicting_claims'] ?? 0) ?></strong></li>
        </ul>
        <p>
            Everything else goes to a family administrator, with the full explanation attached. Administrators can ask
            for more information, approve, decline with a recorded reason, and applicants can appeal.
        </p>

        <h2>What Family Roots does not do</h2>
        <ul>
            <li>It does not run DNA tests or biometric identification, and none is required to use the platform.</li>
            <li>It does not treat a matching score as proof of biological ancestry.</li>
            <li>It does not reject an applicant because their branch is missing. Missing information is recorded as
                missing and kept for review.</li>
            <li>It does not invent ancestors to complete a tree.</li>
            <li>It stores cited evidence as text in the database, never as files in the public web directory.</li>
        </ul>

        <h2>Who runs what</h2>
        <p>
            Family founders and administrators manage their own family: profile, tree, applications, members and
            cited sources. Platform administrators can verify family profiles and handle abuse, but they do not gain
            automatic access to a family's private records, and they can never approve their own application - no account
            can.
        </p>

        <h2>Demo data notice</h2>
        <p class="mb-0">
            The BH KIRU profile shipped with this installation is a demonstration profile. Its ancestors are clearly
            labelled fictional sample records created to exercise the tree, matching engine and review workflow. No real
            BH KIRU family history is claimed or asserted anywhere in this application.
        </p>
    </div>
</section>
