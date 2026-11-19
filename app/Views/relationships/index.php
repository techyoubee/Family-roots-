<?php
/**
 * Relationship list and add-link form for one family tree.
 *
 * @var string|null $title
 * @var array      $family
 * @var array      $relationships
 * @var array      $people       all ancestors (id => display data) for the select fields
 * @var bool       $canWrite
 * @var bool       $canAdmin
 */
$baseUrl = '/families/' . rawurlencode($family['public_id']) . '/relationships';
$ancestorBase = '/families/' . rawurlencode($family['public_id']) . '/ancestors';
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">&larr; Back to the family profile</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Relationships &middot; <?= e($family['public_id']) ?></p>
                <h1>Documented relationships</h1>
                <p class="text-muted">
                    Parent/child and partner links between people in this tree. Cycles and duplicates are
                    rejected; every link records whether it is confirmed, proposed or disputed.
                </p>
            </div>
            <div>
                <?php if (route_exists('ancestors.index')): ?>
                    <a class="btn btn--outline" href="<?= e(route('ancestors.index', ['family' => $family['public_id']])) ?>">Ancestor records</a>
                <?php endif; ?>
                <?php if (route_exists('tree.show')): ?>
                    <a class="btn btn--outline" href="<?= e(route('tree.show', ['family' => $family['public_id']])) ?>">Family tree</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canWrite && count($people) >= 2): ?>
            <div class="card mb-4">
                <h2>Record a relationship</h2>
                <form class="form" method="post" action="<?= e(url($baseUrl)) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-row form-row--split">
                        <div>
                            <label for="relationship_kind">Kind</label>
                            <select id="relationship_kind" name="relationship_kind" required>
                                <option value="parent_of">Parent of (from = parent, to = child)</option>
                                <option value="spouse_of">Partner of</option>
                            </select>
                        </div>
                        <div>
                            <label for="parent_role">Parent role (parent_of only)</label>
                            <select id="parent_role" name="parent_role">
                                <option value="unknown">Unknown</option>
                                <option value="father">Father</option>
                                <option value="mother">Mother</option>
                                <option value="parent">Parent (role not recorded)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row form-row--split">
                        <div>
                            <label for="from_ancestor_id">From person</label>
                            <select id="from_ancestor_id" name="from_ancestor_id" required>
                                <option value="">Choose a person...</option>
                                <?php foreach ($people as $person): ?>
                                    <option value="<?= e((int) $person['id']) ?>"><?= e(\App\Models\Ancestor::displayName($person) . ' (gen ' . (int) $person['generation'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo error('from_ancestor_id'); ?>
                        </div>
                        <div>
                            <label for="to_ancestor_id">To person</label>
                            <select id="to_ancestor_id" name="to_ancestor_id" required>
                                <option value="">Choose a person...</option>
                                <?php foreach ($people as $person): ?>
                                    <option value="<?= e((int) $person['id']) ?>"><?= e(\App\Models\Ancestor::displayName($person) . ' (gen ' . (int) $person['generation'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo error('to_ancestor_id'); ?>
                        </div>
                    </div>
                    <div class="form-row form-row--split-3">
                        <div>
                            <label for="relationship_type">Type</label>
                            <select id="relationship_type" name="relationship_type">
                                <option value="unknown">Unknown</option>
                                <option value="biological">Biological</option>
                                <option value="adopted">Adopted</option>
                                <option value="step">Step</option>
                                <option value="foster">Foster</option>
                            </select>
                        </div>
                        <?php if ($canAdmin): ?>
                            <div>
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="proposed">Proposed</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="disputed">Disputed</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label for="union_year">Union year</label>
                            <input type="number" id="union_year" name="union_year" min="1000" max="2100" placeholder="e.g. 1958">
                            <?php echo error('union_year'); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="notes">Notes</label>
                        <input type="text" id="notes" name="notes" maxlength="500" placeholder="Anything the family should remember about this link">
                    </div>
                    <div class="form-actions">
                        <button class="btn btn--primary" type="submit">Record the relationship</button>
                        <p class="hint hint--flush">Members' links are stored as proposed until an administrator confirms them.</p>
                    </div>
                </form>
            </div>
        <?php elseif ($canWrite): ?>
            <div class="alert alert--info" role="status">
                <div>Add at least two ancestor records before linking people together.</div>
            </div>
        <?php endif; ?>

        <?php if (empty($relationships)): ?>
            <div class="empty-state">
                <h3>No relationships recorded yet</h3>
                <p>Once parents, partners and children are linked, the family tree page shows them connected.</p>
            </div>
        <?php else: ?>
            <div class="card card--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Relationship</th>
                                <th scope="col">Details</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relationships as $edge): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url($ancestorBase . '/' . (int) $edge['from_ancestor_id'])) ?>"><?= e(trim($edge['from_given'] . ' ' . $edge['from_surname'])) ?></a>
                                        <?= $edge['relationship_kind'] === 'parent_of' ? 'is the parent of' : 'is partnered with' ?>
                                        <a href="<?= e(url($ancestorBase . '/' . (int) $edge['to_ancestor_id'])) ?>"><?= e(trim($edge['to_given'] . ' ' . $edge['to_surname'])) ?></a>
                                    </td>
                                    <td class="text-small">
                                        <?php if ($edge['relationship_kind'] === 'parent_of'): ?>
                                            Role: <?= e(status_label($edge['parent_role'])) ?> &middot;
                                        <?php endif; ?>
                                        Type: <?= e(status_label($edge['relationship_type'])) ?>
                                        <?php if (!empty($edge['union_year'])): ?>
                                            &middot; <?= e((int) $edge['union_year']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($edge['notes'])): ?>
                                            <div class="text-muted"><?= e($edge['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= status_badge($edge['status']) ?></td>
                                    <td>
                                        <?php if ($canAdmin): ?>
                                            <form method="post" action="<?= e(url($baseUrl . '/' . (int) $edge['id'] . '/delete')) ?>" class="form" data-confirm="Delete this relationship link?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn--danger btn--small" type="submit">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
