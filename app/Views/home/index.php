<?php
/**
 * @var array $stats
 * @var array $featured
 * @var bool  $dbReady
 */
?>
<section class="hero">
    <div class="wrap">
        <div class="hero__grid">
            <div>
                <p class="eyebrow">Documented ancestry, honest matching</p>
                <h1>Register your family. Build a five-generation tree. Verify relatives properly.</h1>
                <p class="lead">
                    Family Roots gives every family its own private space for its history, its ancestors and its
                    administrators - and connects people through relationships that can be checked, reviewed and
                    corrected. A shared surname is never treated as proof of belonging.
                </p>
                <div class="hero__actions">
                    <?php if (route_exists('family.register')): ?>
                        <a class="btn btn--primary" href="<?= e(route('family.register')) ?>">Register a family</a>
                    <?php endif; ?>
                    <?php if (route_exists('auth.register')): ?>
                        <a class="btn btn--ghost-light" href="<?= e(route('auth.register')) ?>">Create an account</a>
                    <?php endif; ?>
                    <?php if (route_exists('families.index')): ?>
                        <a class="btn btn--ghost-light" href="<?= e(route('families.index')) ?>">Browse the directory</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero__stats" role="list">
                <div class="stat" role="listitem">
                    <div class="stat__value"><?= e(number_format($stats['families'])) ?></div>
                    <div class="stat__label">Family profiles registered</div>
                </div>
                <div class="stat" role="listitem">
                    <div class="stat__value"><?= e(number_format($stats['verified'])) ?></div>
                    <div class="stat__label">Histories verified by review</div>
                </div>
                <div class="stat" role="listitem">
                    <div class="stat__value"><?= e(number_format($stats['ancestors'])) ?></div>
                    <div class="stat__label">Ancestor records documented</div>
                </div>
                <div class="stat" role="listitem">
                    <div class="stat__value"><?= e(number_format($stats['members'])) ?></div>
                    <div class="stat__label">Active family members</div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!$dbReady): ?>
    <div class="wrap flash-region">
        <div class="alert alert--warning" role="alert">
            <div>
                The database is not reachable yet. Run the installer
                (<code>php database/install.php --db-pass=YOUR_MYSQL_PASSWORD</code>) and reload this page.
            </div>
        </div>
    </div>
<?php endif; ?>

<section class="section">
    <div class="wrap">
        <div class="section__head">
            <p class="eyebrow">How it works</p>
            <h2>Five steps, none of them guesswork</h2>
            <p>Every application follows a documented path. You always know where your case stands and what happens next.</p>
        </div>
        <div class="grid grid--3">
            <div class="step">
                <span class="step__num" aria-hidden="true">1</span>
                <div>
                    <h3>Register your account</h3>
                    <p class="text-small text-muted">Confirm your email, choose a strong password and sign in securely.</p>
                </div>
            </div>
            <div class="step">
                <span class="step__num" aria-hidden="true">2</span>
                <div>
                    <h3>Declare your ancestry</h3>
                    <p class="text-small text-muted">Enter your parents, grandparents and older relatives exactly as you know them. Uncertainty is allowed - and recorded honestly.</p>
                </div>
            </div>
            <div class="step">
                <span class="step__num" aria-hidden="true">3</span>
                <div>
                    <h3>Explainable matching</h3>
                    <p class="text-small text-muted">The engine compares each claim with recorded relationships, names, dates and evidence, then explains what matched and what did not.</p>
                </div>
            </div>
            <div class="step">
                <span class="step__num" aria-hidden="true">4</span>
                <div>
                    <h3>Human review</h3>
                    <p class="text-small text-muted">Ambiguous or conflicting cases go to a family administrator. Only a person - never a score - decides.</p>
                </div>
            </div>
            <div class="step">
                <span class="step__num" aria-hidden="true">5</span>
                <div>
                    <h3>Connect and correct</h3>
                    <p class="text-small text-muted">Once approved, you can explore your branch, find relatives and help keep your family's records accurate.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section--alt">
    <div class="wrap">
        <div class="section__head">
            <p class="eyebrow">Built for real families</p>
            <h2>What the platform does</h2>
        </div>
        <div class="grid grid--3">
            <div class="feature">
                <h3>Family registration</h3>
                <p>Founders register a family name, history and origins, appoint administrators and receive a unique family identifier. New profiles start unverified until a review supports them.</p>
            </div>
            <div class="feature">
                <h3>Five-generation trees</h3>
                <p>Explicit parent&ndash;child records, spouses, siblings and branches, with unknown and disputed people clearly marked. Nothing is invented to fill a gap.</p>
            </div>
            <div class="feature">
                <h3>Explainable matching</h3>
                <p>Each application is scored claim by claim. You see which relationship matched, on which evidence, and which parts need a human decision.</p>
            </div>
            <div class="feature">
                <h3>Verification workflow</h3>
                <p>Draft, submitted, matched, review, more information required, approved, declined and appeal - all recorded with timestamps and reasons.</p>
            </div>
            <div class="feature">
                <h3>Privacy by default</h3>
                <p>Private families are invisible in the directory, ancestor records stay inside their family, evidence is recorded as text rather than public files, and every decision is auditable.</p>
            </div>
            <div class="feature">
                <h3>Audited decisions</h3>
                <p>Approvals, declines and membership changes are written to an append-only audit trail, so every decision stays explainable.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="split mb-5">
            <div>
                <p class="eyebrow">Directory</p>
                <h2 class="mb-0">Families you can discover</h2>
            </div>
            <?php if (route_exists('families.index')): ?>
                <a class="btn btn--outline" href="<?= e(route('families.index')) ?>">View all families</a>
            <?php endif; ?>
        </div>

        <?php if (empty($featured)): ?>
            <div class="empty-state">
                <h3>No public family profiles yet</h3>
                <p>Families decide whether their profile is listed. Private families and their records stay hidden.</p>
                <?php if (route_exists('family.register')): ?>
                    <p><a class="btn btn--primary" href="<?= e(route('family.register')) ?>">Register the first family</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid--3">
                <?php foreach ($featured as $family): ?>
                    <?php include base_path('app/Views/partials/family-card.php'); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--alt">
    <div class="wrap">
        <div class="card card--wide">
            <p class="eyebrow">Our promise</p>
            <h2>What a match score is - and is not</h2>
            <p>
                Matching scores compare documented relationships, names (including spelling variants), dates and
                available evidence. They route applications: towards automatic approval when strong, documented rules
                are met, and towards a family administrator when anything is uncertain.
            </p>
            <p class="mb-0">
                <strong>A score never proves biological ancestry.</strong> Family Roots does not use DNA testing,
                biometric identification or external identity checks, and a missing branch in our database is never
                treated as proof that someone belongs to a different family. Applications are kept for review instead.
            </p>
        </div>
    </div>
</section>
