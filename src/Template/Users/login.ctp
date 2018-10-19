<?php
use Cake\Core\Configure;
?>
<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?= $this->Form->create() ?>
    <fieldset>
        <legend>
            <?= __d('CakeDC/Users', 'Please enter your email address and password') ?>
        </legend>
        <?= $this->Form->control(
            'email',
            [
                'label' => 'Email',
                'required' => true
            ]
        ) ?>
        <?= $this->Form->control(
            'password',
            [
                'label' => __d('CakeDC/Users', 'Password'),
                'required' => true
            ]
        ) ?>
        <?php
            if (Configure::read('Users.reCaptcha.login')) {
                echo $this->User->addReCaptcha();
            }
            if (Configure::read('Users.RememberMe.active')) {
                echo $this->Form->control(Configure::read('Users.Key.Data.rememberMe'), [
                    'type' => 'checkbox',
                    'label' => __d('CakeDC/Users', 'Remember me'),
                    'checked' => Configure::read('Users.RememberMe.checked')
                ]);
            }
            $registrationActive = Configure::read('Users.Registration.active');
            if ($registrationActive) {
                echo $this->Html->link(__d('CakeDC/Users', 'Register'), ['action' => 'register']);
            }
            echo $this->Html->link(
                __d('CakeDC/Users', 'Reset Password'),
                ['action' => 'requestResetPassword']
            );
        ?>
    </fieldset>
    <?= implode(' ', $this->User->socialLoginList()); ?>
    <?= $this->Form->button(__d('CakeDC/Users', 'Login')); ?>
    <?= $this->Form->end() ?>
</div>
