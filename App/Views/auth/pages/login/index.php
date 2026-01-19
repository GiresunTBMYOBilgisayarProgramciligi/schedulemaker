<!--begin::Body-->
<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <a href="/admin" class="link-dark text-center link-offset-2 link-opacity-100 link-opacity-50-hover">
                    <h1 class="mb-0"><b>TMYO</b>Takvim</h1>
                </a>
            </div>
            <div class="card-body login-card-body">
                <p class="login-box-msg">Başlamak için giriş yapın</p>
                <form action="/auth/ajaxlogin" method="post" class="ajaxForm" title="Giriş Yap" data-toast="true"
                    data-redirect-delay="1000">
                    <div class="input-group mb-1">
                        <div class="form-floating">
                            <input id="loginEmail" type="email" class="form-control" value="" placeholder="" name="mail"
                                required />
                            <label for="loginEmail">Email</label>
                        </div>
                        <div class="input-group-text"><span class="bi bi-envelope"></span></div>
                    </div>
                    <div class="input-group mb-1">
                        <div class="form-floating">
                            <input id="loginPassword" type="password" class="form-control" placeholder=""
                                name="password" required />
                            <label for="loginPassword">Parola</label>
                        </div>
                        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                    </div>
                    <!--begin::Row-->
                    <div class="row">
                        <div class="col-8 d-inline-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault"
                                    name="remember_me" />
                                <label class="form-check-label" for="flexCheckDefault"> Beni hatırla </label>
                            </div>
                        </div>
                        <!-- /.col -->
                        <div class="col-4">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Giriş Yap</button>
                            </div>
                        </div>
                        <!-- /.col -->
                    </div>
                    <!--end::Row-->
                </form>
            </div>
            <!-- /.login-card-body -->
        </div>
    </div>
    <!-- /.login-box -->
</body>
<!--end::Body-->