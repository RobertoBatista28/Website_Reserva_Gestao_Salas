# Website de Reservas e Gestao de Salas
Este projeto consiste numa aplicação web desenvolvida em PHP com MySQL, destinada à reserva e gestão de salas. Este projeto foca-se em segurança e eficiência, garantindo uma experiência de utilizador robusta e protegida.

## Funcionalidades
### Área Pública
- Listagem de todas as salas disponíveis, visível a utilizadores autenticados e não autenticados.

### Área de Utilizador
- Gestão de Perfil: Visualização e edição de informações pessoais e palavra-passe.
- Requisição de Salas: Reservas de salas para dias e horários específicos.
- Gestão de Salas Reservadas: Visualização, edição e cancelamento de reservas.

### Área de Administração
- Gestão de Salas: Criação, edição e remoção de salas.
- Gestão de Reservas: Monitorização e edição de reservas realizadas por utilizadores.
- Gestão de Utilizadores: Edição e eliminação de contas de utilizadores.
- Gestão de Perfil: Atualização segura de informações e palavra-passe do administrador.

## Medidas de Segurança
- **Hashing de Palavras-Passe:** Utilização de bcrypt com salt único para cada utilizador.
- **Sanitização e Validação de Dados:** Prevenção de ataques de SQL Injection.
- **Requisitos de Complexidade de Palavras-Passe:** Garantia de senhas fortes.
- **Proteção de Sessões:** Verificação de sessão e autenticação baseada em tokens.
- **Gestão de Logs:** Registo de atividades críticas, com limpeza automática de registos antigos.

## Tecnologias Utilizadas
- **Frontend:** HTML, CSS, JavaScript.
- **Backend:** PHP.
- **Base de Dados:** MySQL.
- **Servidor:** Apache (via XAMPP).
- **Email Seguro:** PHPMailer.
- **Gestão de Código:** GitHub.

## Instalação e Configuração
1. Clone o repositório:
   ```bash
   git clone https://github.com/RobertoBatista28/Website_Reserva_Gestao_Salas.git

2. Configure o ambiente no XAMPP:
- Copie os ficheiros para a dentro da pasta xampp.
- Inicie o Apache e MySQL no painel de controle do XAMPP.

3. Configure a base de dados:
- Importe o ficheiro SQL fornecido para criar a estrutura da base de dados.

4. Configure o arquivo conexao.php com as credenciais da base de dados.

5. s

## Estrutura do Projeto
```
Reserva_Gestao_Salas/
├── config/
│   └── ...
├── logs/
│   └── ...
├── public_html/
│   ├── resources/
│   │   ├── areaAdmin/
│   │   ├── header/
│   │   ├── login/
│   │   └── media/
│   │   └── perfil/
│   │   └── recuperarPassword/
│   │   └── registar/
│   │   └── reservarSala/
│   │   └── ...
│   ├── vendor/
│   │   ├── composer/
│   │   └── phpmailer/
│   └── ...
└── ... 
```

## Licença
Este projeto é apenas para uso académico e não está licenciado para uso comercial.
