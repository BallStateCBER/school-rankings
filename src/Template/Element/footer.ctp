<footer class="flex-row">
    <div class="container">
        <div class="row">
            <div class="col-4" id="welcome-contact">
                <h2>
                    Info / Contact
                </h2>
                <p>
                    Fortiss unda, tanquam castus abaculus.
                </p>
                <ul class="list-unstyled">
                    <li>
                        <?= $this->Html->link(
                            'Terms of Use',
                            [
                                'controller' => 'Pages',
                                'action' => 'terms'
                            ]
                        ) ?>
                    </li>
                    <li>
                        <?= $this->Html->link(
                            'Privacy Policy',
                            [
                                'controller' => 'Pages',
                                'action' => 'privacy'
                            ]
                        ) ?>
                    </li>
                </ul>
            </div>
            <div class="col-4">
                <h2>
                    Credits
                </h2>
                <p>
                    Sunt domuses fallere castus, mirabilis fugaes.
                </p>
            </div>
            <div class="col-4">
                <h2>
                    Sponsors
                </h2>
                <p>
                    Magnum parma recte contactuss lumen est.
                </p>
            </div>
        </div>
    </div>
</footer>
