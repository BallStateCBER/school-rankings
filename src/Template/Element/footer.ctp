<footer class="flex-row">
    <div class="container">
        <div class="row">
            <section class="col-4" id="welcome-contact">
                <h3>Center for Business and Economic Research</h3>
                <address>
                    Ball State University<br />
                    Whitinger Business Building, room 149<br />
                    2000 W. University Ave.<br />
                    Muncie, IN 47306-0360
                </address>
            </section>
            <section class="col-3 offset-1">
                <h2>
                    Contact
                </h2>
                <dl>
                    <dt>Phone:</dt>
                    <dd>765-285-5926</dd>

                    <dt>Email:</dt>
                    <dd><a href="mailto:cber@bsu.edu">cber@bsu.edu</a></dd>

                    <dt>Website:</dt>
                    <dd><a href="https://www.bsu.edu/cber">bsu.edu/cber</a></dd>

                    <dt>Facebook:</dt>
                    <dd><a href="https://www.facebook.com/BallStateCBER">/BallStateCBER</a></dd>

                    <dt>Twitter:</dt>
                    <dd><a href="https://www.twitter.com/BallStateCBER">/BallStateCBER</a></dd>
                </dl>
            </section>
            <section class="col-3 offset-1">
                <h2>
                    Legal
                </h2>
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
            </section>
        </div>
    </div>
</footer>
