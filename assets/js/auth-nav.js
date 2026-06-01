document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-logout]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;

            try {
                await fetch('../api/auth/logout.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                });
            } finally {
                window.location.href = 'login.php';
            }
        });
    });
});
