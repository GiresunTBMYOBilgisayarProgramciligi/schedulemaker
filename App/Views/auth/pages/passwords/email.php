<!--begin::Body-->
<body class="login-page bg-body-secondary" data-overlayscrollbars-initialize>
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <a href="/" class="link-dark text-center link-offset-2 link-opacity-100 link-opacity-50-hover">
                    <h1 class="mb-0"><b>TMYO</b>Takvim</h1>
                </a>
            </div>
            <div class="card-body login-card-body">
                <p class="login-box-msg">Şifrenizi mi unuttunuz? Buradan yeni bir şifre talep edebilirsiniz.</p>
                <form action="/ajax/forgotpassword" method="post" class="ajaxForm" title="Şifremi Unuttum" data-toast="true"
                    data-redirect-delay="3000">
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input id="loginEmail" type="email" class="form-control" value="" placeholder="" name="email"
                                required />
                            <label for="loginEmail">Email</label>
                        </div>
                        <div class="input-group-text"><span class="bi bi-envelope"></span></div>
                    </div>
                    <!--begin::Row-->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Yeni Şifre İste</button>
                            </div>
                        </div>
                    </div>
                    <!--end::Row-->
                </form>
                <p class="mb-1 mt-3">
                    <a href="/auth/login">Giriş sayfasına dön</a>
                </p>
            </div>
            <!-- /.login-card-body -->
        </div>
    </div>
    <!-- /.login-box -->
</body>
<!--end::Body-->
