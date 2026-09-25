// 1. Seleciona o botão de criar conta (altere 'btn-criar-conta' se o ID do seu botão for diferente)
const btnCriarConta = document.getElementById('btn-criar-conta');

if (btnCriarConta) {
    btnCriarConta.addEventListener('click', function(event) {
        event.preventDefault(); // Evita que a página recarregue

        // 2. Pega o que o usuário DIGITOU de verdade nos inputs
        // (Ajuste os IDs ('email', 'senha') se os nomes no seu HTML forem diferentes)
        const emailDigitado = document.getElementById('email').value;
        const senhaDigitada = document.getElementById('senha').value;

        // 3. Validação da regra do TecFlow: Senha mínima de 8 caracteres
        if (senhaDigitada.length < 8) {
            alert("A senha deve conter no mínimo 8 caracteres.");
            return; // Para o cadastro se a senha for curta
        }

        // 4. Cria o usuário com a SENHA PERSONALIZADA (adeus "Teste123")
        const novoUsuario = {
            email: emailDigitado,
            senha: senhaDigitada 
        };

        // 5. Salva no navegador (localStorage) ou manda para sua lista
        let listaUsuarios = JSON.parse(localStorage.getItem('usuarios_tecflow')) || [];
        
        // Verifica se o e-mail já foi cadastrado
        const emailExiste = listaUsuarios.some(user => user.email === emailDigitado);
        if (emailExiste) {
            alert("Este e-mail já está cadastrado!");
            return;
        }

        listaUsuarios.push(novoUsuario);
        localStorage.setItem('usuarios_tecflow', JSON.stringify(listaUsuarios));

        alert("Conta criada com sucesso com sua nova senha!");
    });
}
    // Código isolado do menu hambúrguer
    const btnMenu = document.getElementById('btn-menu');
    const menuLinks = document.getElementById('menu-links');

    if (btnMenu && menuLinks) {
        btnMenu.addEventListener('click', () => {
            menuLinks.classList.toggle('ativo');
        });

        // Opcional: fecha o menu sozinho quando clica em algum link pelo celular
        const linksMenu = menuLinks.querySelectorAll('a');
        linksMenu.forEach(link => {
            link.addEventListener('click', () => {
                menuLinks.classList.remove('ativo');
            });
        });
    }
// 1. Aplica o tema imediatamente para evitar "flash" de tela branca
(function () {
    try {
        var t = localStorage.getItem('tema');
        if (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            t = 'dark';
        }
        document.documentElement.setAttribute('data-theme', t || 'light');
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
    }
})();

// 2. Atualiza o ícone do botão com base no tema atual
function updateThemeIcon() {
    const btn = document.getElementById('themeToggle');
    const atual = document.documentElement.getAttribute('data-theme');
    
    if (btn) {
        btn.innerHTML = atual === 'dark' 
            ? '<i class="fa-solid fa-sun"></i>' 
            : '<i class="fa-solid fa-moon"></i>';
    }
}

// 3. Alterna o tema ao clicar no botão
function toggleTheme() {
    const atual = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = atual === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    
    try {
        localStorage.setItem('tema', newTheme);
    } catch (e) {}
    
    updateThemeIcon();
}

// 4. Garante que o ícone correto seja carregado assim que a página abrir
document.addEventListener('DOMContentLoaded', updateThemeIcon);