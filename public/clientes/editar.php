<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin', 'gestor', 'comercial']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    abort(400, 'ID de cliente inválido.');
}

$cliente = run_query('SELECT * FROM clientes WHERE id = ?', [$id])[0] ?? null;
if (!$cliente) {
    abort(404, 'Cliente não encontrado.');
}

$dados = $cliente;
$dados['cnpj'] = preg_replace('/\D+/', '', (string)($dados['cnpj'] ?? ''));
$dados['telefone'] = preg_replace('/\D+/', '', (string)($dados['telefone'] ?? ''));
$dados['cep'] = preg_replace('/\D+/', '', (string)($dados['cep'] ?? ''));
$dados['uf'] = strtoupper(substr((string)($dados['uf'] ?? ''), 0, 2));
$dados['qtd_colaboradores'] = (string)($dados['qtd_colaboradores'] ?? '0');

$contatos = run_query(
    'SELECT id, nome, cargo, email, telefone, principal FROM contatos_clientes WHERE id_cliente = ? ORDER BY principal DESC, nome ASC',
    [$id]
);

$mensagem = $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);

    $camposPermitidos = [
        'nome_fantasia',
        'razao_social',
        'cnpj',
        'email',
        'telefone',
        'endereco',
        'bairro',
        'cep',
        'cidade',
        'uf',
        'qtd_colaboradores',
        'status',
        'origem',
    ];

    $novosDados = [];
    foreach ($camposPermitidos as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        if (in_array($campo, ['cnpj', 'telefone', 'cep'], true)) {
            $valor = preg_replace('/\D+/', '', $valor);
        }
        if ($campo === 'uf') {
            $valor = strtoupper(substr($valor, 0, 2));
        }
        if ($campo === 'qtd_colaboradores') {
            $valor = (string)max(0, (int)$valor);
        }
        $novosDados[$campo] = $valor;
    }

    $contatosJson = $_POST['contacts'] ?? '[]';
    $contatosInput = json_decode($contatosJson, true);
    if (!is_array($contatosInput)) {
        $erro = 'Formato de contatos inválido.';
    }

    $contatosSanitizados = [];
    if (!$erro) {
        $principalAtribuido = false;
        foreach ($contatosInput as $contato) {
            $nome = trim($contato['nome'] ?? '');
            $email = trim($contato['email'] ?? '');
            $cargo = trim($contato['cargo'] ?? '');
            $telefone = preg_replace('/\D+/', '', (string)($contato['telefone'] ?? ''));
            $principal = !empty($contato['principal']) ? 1 : 0;
            $contatoId = (int)($contato['id'] ?? 0);

            if ($nome === '') {
                $erro = 'Informe o nome para todos os contatos.';
                break;
            }

            if ($principal) {
                if ($principalAtribuido) {
                    $principal = 0;
                } else {
                    $principalAtribuido = true;
                }
            }

            $contatosSanitizados[] = [
                'id'        => $contatoId,
                'nome'      => $nome,
                'cargo'     => $cargo,
                'email'     => $email,
                'telefone'  => $telefone,
                'principal' => $principal,
            ];
        }

        if (!$principalAtribuido && $contatosSanitizados) {
            $contatosSanitizados[0]['principal'] = 1;
        }
    }

    if ($novosDados['nome_fantasia'] === '') {
        $erro = 'Informe o nome fantasia.';
    }

    if (!$erro) {
        $pdo = pdo();
        try {
            $pdo->beginTransaction();

            $camposParaAtualizar = [];
            $valoresAtualizar = [];

            foreach ($novosDados as $campo => $valor) {
                $valorAtual = (string)($cliente[$campo] ?? '');
                if ($valorAtual !== $valor) {
                    $camposParaAtualizar[] = "$campo = ?";
                    $valoresAtualizar[] = $valor;
                }
            }

            if ($camposParaAtualizar) {
                $valoresAtualizar[] = $id;
                $stmtCliente = $pdo->prepare(
                    'UPDATE clientes SET ' . implode(', ', $camposParaAtualizar) . ', atualizado_em = CURRENT_TIMESTAMP WHERE id = ?'
                );
                $stmtCliente->execute($valoresAtualizar);
            }

            $stmtCurrent = $pdo->prepare('SELECT id FROM contatos_clientes WHERE id_cliente = ?');
            $stmtCurrent->execute([$id]);
            $idsAtuais = array_map('intval', $stmtCurrent->fetchAll(PDO::FETCH_COLUMN));

            $idsMantidos = [];
            $stmtInsert = $pdo->prepare(
                'INSERT INTO contatos_clientes (id_cliente, nome, cargo, email, telefone, principal)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmtUpdate = $pdo->prepare(
                'UPDATE contatos_clientes
                 SET nome = ?, cargo = ?, email = ?, telefone = ?, principal = ?
                 WHERE id = ? AND id_cliente = ?'
            );

            foreach ($contatosSanitizados as $contato) {
                if ($contato['id'] > 0 && in_array($contato['id'], $idsAtuais, true)) {
                    $stmtUpdate->execute([
                        $contato['nome'],
                        $contato['cargo'],
                        $contato['email'],
                        $contato['telefone'],
                        $contato['principal'],
                        $contato['id'],
                        $id,
                    ]);
                    $idsMantidos[] = $contato['id'];
                } else {
                    $stmtInsert->execute([
                        $id,
                        $contato['nome'],
                        $contato['cargo'],
                        $contato['email'],
                        $contato['telefone'],
                        $contato['principal'],
                    ]);
                    $idsMantidos[] = (int)$pdo->lastInsertId();
                }
            }

            $idsRemover = array_diff($idsAtuais, $idsMantidos);
            if ($idsRemover) {
                $placeholders = implode(',', array_fill(0, count($idsRemover), '?'));
                $stmtDelete = $pdo->prepare("DELETE FROM contatos_clientes WHERE id_cliente = ? AND id IN ($placeholders)");
                $stmtDelete->execute(array_merge([$id], $idsRemover));
            }

            $pdo->commit();

            log_user_action(
                current_user()['id'] ?? null,
                'Edição de cliente',
                'clientes',
                $id,
                $cliente + ['contatos' => $contatos],
                $novosDados + ['contatos' => $contatosSanitizados]
            );

            $mensagem = 'Cliente atualizado com sucesso!';
            $cliente = run_query('SELECT * FROM clientes WHERE id = ?', [$id])[0] ?? $cliente;
            $dados = $cliente;
            $dados['cnpj'] = preg_replace('/\D+/', '', (string)($dados['cnpj'] ?? ''));
            $dados['telefone'] = preg_replace('/\D+/', '', (string)($dados['telefone'] ?? ''));
            $dados['cep'] = preg_replace('/\D+/', '', (string)($dados['cep'] ?? ''));
            $dados['uf'] = strtoupper(substr((string)($dados['uf'] ?? ''), 0, 2));
            $dados['qtd_colaboradores'] = (string)($dados['qtd_colaboradores'] ?? '0');
            $contatos = run_query(
                'SELECT id, nome, cargo, email, telefone, principal
                 FROM contatos_clientes
                 WHERE id_cliente = ?
                 ORDER BY principal DESC, nome ASC',
                [$id]
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Falha ao atualizar o cliente. Tente novamente.';
            log_system('error', 'Falha ao atualizar cliente: ' . $e->getMessage(), __FILE__, __LINE__, $e->getTraceAsString());
            $contatos = $contatosSanitizados;
        }
    } else {
        $contatos = $contatosSanitizados;
    }
}

$contatosJsonInicial = json_encode($contatos, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
$page_title = 'Editar Cliente';
$breadcrumb = 'Clientes > Edição';

ob_start();
?>
<?php if ($mensagem): ?>
  <div class="alert alert-success"><?= e($mensagem) ?></div>
<?php elseif ($erro): ?>
  <div class="alert alert-danger"><?= e($erro) ?></div>
<?php endif; ?>

<form method="POST" class="card p-4 shadow-sm">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Nome Fantasia *</label>
      <input type="text" name="nome_fantasia" class="form-control" value="<?= e($dados['nome_fantasia']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Raz&atilde;o Social</label>
      <input type="text" name="razao_social" class="form-control" value="<?= e($dados['razao_social']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">CNPJ</label>
      <input type="text" name="cnpj" class="form-control" data-mask="cnpj" value="<?= e($dados['cnpj']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">E-mail</label>
      <input type="email" name="email" class="form-control" value="<?= e($dados['email']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Telefone</label>
      <input type="text" name="telefone" class="form-control" data-mask="phone" value="<?= e($dados['telefone']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Endere&ccedil;o</label>
      <input type="text" name="endereco" class="form-control" value="<?= e($dados['endereco']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Bairro</label>
      <input type="text" name="bairro" class="form-control" value="<?= e($dados['bairro']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">CEP</label>
      <input type="text" name="cep" class="form-control" data-mask="cep" value="<?= e($dados['cep']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Cidade</label>
      <input type="text" name="cidade" class="form-control" value="<?= e($dados['cidade']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">UF</label>
      <input type="text" name="uf" maxlength="2" class="form-control text-uppercase" value="<?= e($dados['uf']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Qtd. de Colaboradores</label>
      <input type="number" name="qtd_colaboradores" class="form-control" min="0" value="<?= e($dados['qtd_colaboradores']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Origem</label>
      <select name="origem" class="form-select">
        <?php foreach (['manual', 'indicação', 'site', 'outro'] as $origem): ?>
          <option value="<?= e($origem) ?>" <?= $dados['origem'] === $origem ? 'selected' : '' ?>><?= e(ucfirst($origem)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['prospecto', 'ativo', 'inativo'] as $status): ?>
          <option value="<?= e($status) ?>" <?= $dados['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-primary mb-0">Contatos</h6>
        <button type="button" class="btn btn-sm btn-success" id="btn-add-contact">&#43; Contato</button>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>Cargo</th>
              <th>E-mail</th>
              <th>Telefone</th>
              <th>Principal</th>
              <th class="text-end">A&ccedil;&otilde;es</th>
            </tr>
          </thead>
          <tbody id="contacts-table-body"></tbody>
        </table>
      </div>
      <p class="text-muted text-center my-3<?= $contatos ? ' d-none' : '' ?>" id="contacts-empty">Nenhum contato adicionado.</p>
      <input type="hidden" name="contacts" id="contacts-json" value="<?= e($contatosJsonInicial) ?>">
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">Salvar Altera&ccedil;&otilde;es</button>
    <a href="<?= e(app_url('clientes/listar.php')) ?>" class="btn btn-outline-secondary">Voltar</a>
  </div>
</form>

<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="contact-form">
      <div class="modal-header">
        <h5 class="modal-title">Contato</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="contact-error"></div>
        <input type="hidden" id="contact-id">
        <div class="mb-3">
          <label class="form-label">Nome *</label>
          <input type="text" class="form-control" id="contact-nome" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Cargo</label>
          <input type="text" class="form-control" id="contact-cargo">
        </div>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" class="form-control" id="contact-email">
        </div>
        <div class="mb-3">
          <label class="form-label">Telefone</label>
          <input type="text" class="form-control" id="contact-telefone" data-mask="phone">
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="contact-principal">
          <label class="form-check-label" for="contact-principal">Contato principal</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar contato</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const maskFormatters = {
    cnpj: (value) => {
      const digits = value.slice(0, 14);
      const parts = [
        digits.slice(0, 2),
        digits.slice(2, 5),
        digits.slice(5, 8),
        digits.slice(8, 12),
        digits.slice(12, 14),
      ];
      let formatted = '';
      if (parts[0]) formatted = parts[0];
      if (parts[1]) formatted += '.' + parts[1];
      if (parts[2]) formatted += '.' + parts[2];
      if (parts[3]) formatted += '/' + parts[3];
      if (parts[4]) formatted += '-' + parts[4];
      return formatted;
    },
    phone: (value) => {
      const digits = value.slice(0, 11);
      if (digits.length <= 2) return digits;
      const ddd = digits.slice(0, 2);
      const middle = digits.length > 10 ? digits.slice(2, 7) : digits.slice(2, 6);
      const end = digits.length > 10 ? digits.slice(7, 11) : digits.slice(6, 10);
      let formatted = `(${ddd})`;
      if (middle) formatted += ` ${middle}`;
      if (end) formatted += `-${end}`;
      return formatted;
    },
    cep: (value) => {
      const digits = value.slice(0, 8);
      if (digits.length <= 5) return digits;
      return `${digits.slice(0, 5)}-${digits.slice(5, 8)}`;
    },
  };

  function applyMask(input) {
    const formatter = maskFormatters[input.dataset.mask];
    if (!formatter) {
      return;
    }
    const digits = input.value.replace(/\D+/g, '');
    input.value = formatter(digits);
  }

  document.querySelectorAll('[data-mask]').forEach((input) => {
    input.addEventListener('input', () => applyMask(input));
    input.addEventListener('blur', () => applyMask(input));
    applyMask(input);
  });

  const contactsInput = document.getElementById('contacts-json');
  const tableBody = document.getElementById('contacts-table-body');
  const emptyState = document.getElementById('contacts-empty');
  const modalElement = document.getElementById('contactModal');
  const modal = new bootstrap.Modal(modalElement);
  const contactForm = document.getElementById('contact-form');
  const contactError = document.getElementById('contact-error');
  const fields = {
    id: document.getElementById('contact-id'),
    nome: document.getElementById('contact-nome'),
    cargo: document.getElementById('contact-cargo'),
    email: document.getElementById('contact-email'),
    telefone: document.getElementById('contact-telefone'),
    principal: document.getElementById('contact-principal'),
  };
  let contacts = [];
  let editingIndex = null;

  try {
    const parsed = JSON.parse(contactsInput.value || '[]');
    if (Array.isArray(parsed)) {
      contacts = parsed;
    }
  } catch (err) {
    contacts = [];
  }

  function formatPhoneDisplay(digits) {
    return digits ? maskFormatters.phone(digits) : '';
  }

  function refreshHiddenInput() {
    contactsInput.value = JSON.stringify(contacts);
  }

  function e(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[char]));
  }

  function renderContacts() {
    tableBody.innerHTML = '';
    if (!contacts.length) {
      emptyState.classList.remove('d-none');
      refreshHiddenInput();
      return;
    }

    emptyState.classList.add('d-none');
    contacts.forEach((contact, index) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${e(contact.nome)}</td>
        <td>${e(contact.cargo || '')}</td>
        <td>${e(contact.email || '')}</td>
        <td>${e(formatPhoneDisplay(contact.telefone || ''))}</td>
        <td>${contact.principal ? '<span class="badge bg-primary">Sim</span>' : 'Não'}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-action="edit" data-index="${index}" title="Editar">&#9998;</button>
          <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-index="${index}" title="Excluir">&#128465;</button>
        </td>
      `;
      tableBody.appendChild(row);
    });

    tableBody.querySelectorAll('[data-action="edit"]').forEach((button) => {
      button.addEventListener('click', () => openContactModal(Number(button.dataset.index)));
    });

    tableBody.querySelectorAll('[data-action="delete"]').forEach((button) => {
      button.addEventListener('click', () => removeContact(Number(button.dataset.index)));
    });

    refreshHiddenInput();
  }

  function openContactModal(index = null) {
    editingIndex = index;
    contactError.classList.add('d-none');
    contactError.textContent = '';

    if (index !== null && contacts[index]) {
      const contact = contacts[index];
      fields.id.value = contact.id || 0;
      fields.nome.value = contact.nome || '';
      fields.cargo.value = contact.cargo || '';
      fields.email.value = contact.email || '';
      fields.telefone.value = contact.telefone || '';
      fields.principal.checked = !!contact.principal;
    } else {
      fields.id.value = '0';
      fields.nome.value = '';
      fields.cargo.value = '';
      fields.email.value = '';
      fields.telefone.value = '';
      fields.principal.checked = !contacts.length;
    }

    applyMask(fields.telefone);
    modal.show();
  }

  function removeContact(index) {
    if (!Number.isInteger(index) || !contacts[index]) {
      return;
    }
    if (confirm('Remover este contato?')) {
      contacts.splice(index, 1);
      if (contacts.length === 1) {
        contacts[0].principal = 1;
      }
      renderContacts();
    }
  }

  document.getElementById('btn-add-contact').addEventListener('click', () => openContactModal(null));

  modalElement.addEventListener('hidden.bs.modal', () => {
    contactForm.reset();
    fields.id.value = '0';
    editingIndex = null;
    contactError.classList.add('d-none');
    contactError.textContent = '';
  });

  contactForm.addEventListener('submit', (event) => {
    event.preventDefault();
    contactError.classList.add('d-none');
    contactError.textContent = '';

    const nome = fields.nome.value.trim();
    if (!nome) {
      contactError.textContent = 'Informe o nome do contato.';
      contactError.classList.remove('d-none');
      return;
    }

    const telefoneDigits = fields.telefone.value.replace(/\D+/g, '').slice(0, 11);
    const contato = {
      id: Number(fields.id.value || 0),
      nome,
      cargo: fields.cargo.value.trim(),
      email: fields.email.value.trim(),
      telefone: telefoneDigits,
      principal: fields.principal.checked ? 1 : 0,
    };

    if (contato.principal) {
      contacts = contacts.map((item, idx) => {
        if (editingIndex !== null && idx === editingIndex) {
          return item;
        }
        return { ...item, principal: 0 };
      });
    }

    if (editingIndex === null || !contacts[editingIndex]) {
      contato.id = 0;
      contacts.push(contato);
    } else {
      contato.id = contacts[editingIndex].id || 0;
      contacts[editingIndex] = contato;
    }

    renderContacts();
    modal.hide();
  });

  renderContacts();
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
