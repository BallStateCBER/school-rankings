<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
use Cake\Core\Configure;

?>
<div class="users form large-10 medium-9 columns">
    <?= $this->Form->create($user); ?>
    <fieldset>
        <legend>
            Create an Account
        </legend>
        <?php
            echo $this->Form->control('email', ['label' => 'Email']);
            echo $this->Form->control('password', ['label' => 'Password']);
            echo $this->Form->control('password_confirm', [
                'type' => 'password',
                'label' => 'Confirm password'
            ]);
            if (Configure::read('Users.reCaptcha.registration')) {
                echo $this->User->addReCaptcha();
            }
        ?>
    </fieldset>
    <?= $this->Form->button('Register') ?>
    <?= $this->Form->end() ?>
</div>
