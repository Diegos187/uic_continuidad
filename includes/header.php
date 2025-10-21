<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="/src/admin/dashboard.php">
            <img src="/public/img/logo_utem.png" alt="" class="img-fluid" style="max-width: 50px; max-height: 50px;"> Sistema de Gestión de Mallas Curriculares
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <span class="nav-link">
                            Bienvenido, <?php echo $_SESSION['nombre'] ?? 'Usuario'; ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../src/admin/mallas.php">Mallas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../src/admin/areas_formacion.php">Áreas de formación</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="logout-link">Cerrar Sesión</a>
                    </li>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>
                        document.getElementById('logout-link').addEventListener('click', function(event) {
                            event.preventDefault();
                            Swal.fire({
                                title: '¿Cerrar sesión?',
                                text: "¿Estás seguro(a) de que deseas cerrar sesión?",
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, cerrar sesión',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = '/src/auth/logout.php';
                                }
                            });
                        });
                    </script>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/src/auth/login.php">Iniciar Sesión</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>