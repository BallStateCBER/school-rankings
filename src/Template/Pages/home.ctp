<div class="jumbotron" id="home-jumbotron">
    <h1 class="display-4">
        Welcome to Indiana School Rankings
    </h1>
    <p class="lead">
        A tool for finding local schools that meet <em>your</em> needs
    </p>
    <hr class="my-4" />
    <p>
        Here, you can rank any of the public, private, and charter PK-12 schools in any Indiana county according to your
        own criteria, such as graduation rate, total enrollment, and AP exam pass rates. We use data from the Indiana
        Department of Education to analyze school or school corporations in your area and rank them according to the
        factors that are most important to you.
    </p>
    <p>
        <?= $this->Html->link(
            'Begin ranking schools',
            [
                'prefix' => false,
                'controller' => 'Formulas',
                'action' => 'form'
            ],
            ['class' => 'btn btn-lg btn-primary']
        ) ?>
    </p>
</div>
