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