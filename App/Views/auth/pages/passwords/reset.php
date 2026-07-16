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
                <p class="login-box-msg">Şifrenizi güvenli bir şekilde sıfırlayabilirsiniz.</p>
                <form action="/ajax/resetpassword" method="post" class="ajaxForm" title="Şifre Sıfırlama" data-toast="true"
                    data-redirect-delay="2000">
                    
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '') ?>">
                    
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input id="loginPassword" type="password" class="form-control" placeholder="" name="password"
                                required />
                            <label for="loginPassword">Yeni Parola</label>
                        </div>
                        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                    </div>
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input id="loginPasswordConfirm" type="password" class="form-control" placeholder="" name="password_confirmation"
                                required />
                            <label for="loginPasswordConfirm">Yeni Parola (Tekrar)</label>
                        </div>
                        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                    </div>
                    <!--begin::Row-->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Şifreyi Değiştir</button>
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
