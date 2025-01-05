<?php
/**
 * @var \App\Controllers\ProgramController $programController
 * @var \App\Models\Program $program
 */
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= $page_title ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Program İşlemleri</li>
                        <li class="breadcrumb-item active">Program</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <?php
        echo "<pre>";
        var_dump($program);
        echo "/<pre>";
        ?>
    </section>
</div>
