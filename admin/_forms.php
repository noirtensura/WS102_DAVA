<?php
declare(strict_types=1);

function admin_status_form(string $operation, int $id, array $choices, bool $withNote = false, string $search = ''): void
{
    if ($choices === []) {
        echo '<span class="muted">No decisions available</span>';
        return;
    }
    ?>
    <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="decision-form">
        <?= csrf_field() ?>
        <input type="hidden" name="operation" value="<?= e($operation) ?>">
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <?php if ($search !== ''): ?><input type="hidden" name="account_search" value="<?= e($search) ?>"><?php endif; ?>
        <?php if (count($choices) === 1): ?>
            <input type="hidden" name="status" value="<?= e(array_key_first($choices)) ?>">
        <?php else: ?>
            <label class="form-field">Decision
                <select name="status" required>
                    <option value="">Choose a decision</option>
                    <?php foreach ($choices as $status => $label): ?>
                        <option value="<?= e($status) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($withNote): ?>
            <label class="form-field">Review note (optional)
                <input type="text" name="note" maxlength="500" placeholder="Add a reason or requested change">
            </label>
        <?php endif; ?>
        <button class="button secondary small" type="submit"<?= $operation === 'user' && isset($choices['suspended']) ? ' data-confirm="Suspend this account? The user will lose access until you reactivate it."' : '' ?>><?= e(count($choices) === 1 ? reset($choices) : 'Save decision') ?></button>
    </form>
    <?php
}

function admin_status_label(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}
