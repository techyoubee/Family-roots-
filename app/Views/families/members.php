<?php
/**
 * Family member management (administrators only).
 *
 * @var string|null $title
 * @var array      $family
 * @var array      $rows   each: member row + canChange/canRemove flags
 * @var string|null $myRole
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">&larr; Back to the family profile</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Member management</p>
                <h1><?= e($family['name']) ?></h1>
                <p class="text-muted">
                    The founder role is fixed. Only the founder can appoint or remove other administrators,
                    and nobody can change their own role or remove themselves from this screen.
                </p>
            </div>
            <div>
                <?php if (route_exists('families.show')): ?>
                    <a class="btn btn--outline" href="<?= e(route('families.show', ['family' => $family['public_id']])) ?>">Family profile</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th scope="col">Member</th>
                            <th scope="col">Role</th>
                            <th scope="col">Linked ancestor</th>
                            <th scope="col">Joined</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $member = $row['member']; ?>
                            <tr>
                                <td>
                                    <strong><?= e($member['full_name']) ?></strong>
                                    <div class="text-small text-muted"><?= e($member['email']) ?></div>
                                </td>
                                <td>
                                    <?php if ($row['canChange']): ?>
                                        <form method="post" action="<?= e(url('/families/' . rawurlencode($family['public_id']) . '/members/' . (int) $member['id'] . '/role')) ?>" class="form form--inline-flex">
                                            <?= csrf_field() ?>
                                            <label class="visually-hidden" for="role-<?= e($member['id']) ?>">Role for <?= e($member['full_name']) ?></label>
                                            <select id="role-<?= e($member['id']) ?>" name="role">
                                                <?php foreach (['admin', 'member', 'viewer'] as $role): ?>
                                                    <option value="<?= e($role) ?>" <?= $member['role'] === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn--outline btn--small" type="submit">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge--neutral"><?= e(ucfirst($member['role'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($member['given_names']) || !empty($member['surname'])): ?>
                                        <?= e(trim(($member['given_names'] ?? '') . ' ' . ($member['surname'] ?? ''))) ?>
                                        <?php if (!empty($member['generation'])): ?>
                                            <div class="text-small text-muted">Generation <?= e((int) $member['generation']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted text-small">Not linked yet</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-small"><?= e(format_date($member['joined_at'])) ?></td>
                                <td>
                                    <?php if ($row['canRemove']): ?>
                                        <form method="post" action="<?= e(url('/families/' . rawurlencode($family['public_id']) . '/members/' . (int) $member['id'] . '/remove')) ?>" class="form" data-confirm="Remove this member from the family? Their account is not deleted.">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--danger btn--small" type="submit">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
