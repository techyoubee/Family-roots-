<?php
/**
 * @var array  $families
 * @var string $search
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="eyebrow">Family directory</p>
        <h1>Discover documented families</h1>
        <p class="text-muted prose">
            Only families that chose to be listed appear here, and only their profile information - never their ancestor
            records. Families with private profiles are not listed and are not discoverable.
        </p>

        <form class="filter-bar card" method="get" action="<?= e(url('/families')) ?>" role="search">
            <div class="form-row form-row--grow">
                <label for="q">Search by family name, origin, region or country</label>
                <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="e.g. BH KIRU, Bhutan, Thimphu">
            </div>
            <div class="form-row">
                <button class="btn btn--primary" type="submit">Search</button>
            </div>
            <?php if ($search !== ''): ?>
                <div class="form-row">
                    <a class="btn btn--outline" href="<?= e(url('/families')) ?>">Clear</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <?php if (empty($families)): ?>
            <?php
            $heading = $search !== '' ? 'No families matched your search' : 'No public family profiles yet';
            $message = $search !== ''
                ? 'Try a different spelling, region or country. Families with private profiles never appear in search results.'
                : 'Families decide whether their profile is listed in the directory.';
            include base_path('app/Views/partials/empty-state.php');
            ?>
        <?php else: ?>
            <p class="text-muted text-small"><?= e(count($families)) ?> family profile<?= count($families) === 1 ? '' : 's' ?> listed</p>
            <div class="grid grid--3">
                <?php foreach ($families as $family): ?>
                    <?php include base_path('app/Views/partials/family-card.php'); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
