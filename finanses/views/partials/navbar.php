<nav class="navbar navbar-expand-lg border-bottom bg-body-tertiary sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/finanses/public/dashboard.php">FINANSES</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/finanses/public/dashboard.php">Главная</a></li>
                <li class="nav-item"><a class="nav-link" href="/finanses/public/requests/list.php">Заявки</a></li>
                <li class="nav-item"><a class="nav-link" href="/finanses/public/materials/index.php">Материалы</a></li>
                <li class="nav-item"><a class="nav-link" href="/finanses/public/files/list.php">Файлы</a></li>
                <li class="nav-item"><a class="nav-link" href="/finanses/public/profile/index.php">Профиль</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-body-secondary"><?php if (!empty($_SESSION['user'])): ?><?= htmlspecialchars($_SESSION['user']['fio']) ?> (<?= htmlspecialchars($_SESSION['user']['role']) ?>)<?php endif; ?></span>
                <form id="logoutForm" method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Finanses\Helpers::csrfToken()) ?>">
                    <button class="btn btn-outline-danger btn-sm" type="button" onclick="window.App.logout()">Выход</button>
                </form>
            </div>
        </div>
    </div>
</nav>
