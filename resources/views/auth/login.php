<?php /** @var string $title */ ?>
<section class="card auth-card">
    <h1>Login</h1>
    <p class="muted">Masuk dengan akun internal IT Portal.</p>

    <form method="post" action="/login" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= e(old('email') ?? '') ?>"
                   autocomplete="username" autofocus required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary">Masuk</button>
    </form>
</section>
