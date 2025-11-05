<?php
require_once __DIR__ . '/../../config/db.php';

ensure_session_security();
require_role(['admin','gestor','comercial']);

$allowedStatus = ['rascunho','enviada','aceita','rejeitada','expirada'];

$idFromRequest = (int)($_GET['id'] ?? 0);
$idFromPost = (int)($_POST['id'] ?? 0);
$propostaId = $idFromPost ?: $idFromRequest;
$editando = $propostaId > 0;

$clientes = run_query('SELECT id, nome_fantasia FROM clientes ORDER BY nome_fantasia ASC');
$pacotes = run_query('SELECT id, nome, descricao, tipo_calculo, sinistralidade_padrao, franquia_padrao, valor_implantacao_base, valor_mensal_base FROM pacotes WHERE ativo = TRUE ORDER BY nome ASC');
$modelos = run_query('SELECT id, titulo, categoria FROM modelos_documentos WHERE ativo = TRUE ORDER BY categoria, titulo ASC');

$propostaAtual = null;
$itensAtuais = [];
if ($editando) {
    $propostaAtual = run_query('SELECT * FROM propostas WHERE id = ?', [$propostaId])[0] ?? null;
    if (!$propostaAtual) {
        abort(404, 'Proposta nao encontrada.');
    }

    $itensAtuais = run_query('SELECT * FROM proposta_itens WHERE id_proposta = ? ORDER BY id ASC', [$propostaId]);
}

$formData = [
    'id' => $propostaId,
    'id_cliente' => $propostaAtual['id_cliente'] ?? (int)($_GET['cliente_id'] ?? 0),
    'id_pacote' => $propostaAtual['id_pacote'] ?? null,
    'modelo_id' => $propostaAtual['modelo_id'] ?? null,
    'numero_colaboradores' => $propostaAtual['numero_colaboradores'] ?? null,
    'sinistralidade_percentual' => $propostaAtual['sinistralidade_percentual'] ?? null,
    'franquia_percentual' => $propostaAtual['franquia_percentual'] ?? null,
    'valor_implantacao' => $propostaAtual['valor_implantacao'] ?? null,
    'valor_mensal' => $propostaAtual['valor_mensal'] ?? null,
    'descricao' => $propostaAtual['descricao'] ?? '',
    'observacoes' => $propostaAtual['observacoes'] ?? '',
    'data_envio' => !empty($propostaAtual['data_envio']) ? date('Y-m-d\TH:i', strtotime($propostaAtual['data_envio'])) : '',
    'validade_dias' => $propostaAtual['validade_dias'] ?? '',
    'status' => $propostaAtual['status'] ?? 'rascunho',
];

$itensForm = array_map(static function (array $item): array {
    return [
        'tipo_item' => $item['tipo_item'],
        'descricao_item' => $item['descricao_item'],
        'quantidade' => (float)$item['quantidade'],
        'valor_unitario' => (float)$item['valor_unitario'],
        'valor_total' => (float)$item['valor_total'],
    ];
}, $itensAtuais);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['_token'] ?? null);

    $formData['id_cliente'] = (int)($_POST['id_cliente'] ?? 0);
    $formData['id_pacote'] = isset($_POST['id_pacote']) && $_POST['id_pacote'] !== '' ? (int)$_POST['id_pacote'] : null;
    $formData['modelo_id'] = isset($_POST['modelo_id']) && $_POST['modelo_id'] !== '' ? (int)$_POST['modelo_id'] : null;
    $formData['numero_colaboradores'] = $_POST['numero_colaboradores'] === '' ? null : max(0, (int)$_POST['numero_colaboradores']);
    $formData['sinistralidade_percentual'] = $_POST['sinistralidade_percentual'] === '' ? null : (float)str_replace(',', '.', $_POST['sinistralidade_percentual']);
    $formData['franquia_percentual'] = $_POST['franquia_percentual'] === '' ? null : (float)str_replace(',', '.', $_POST['franquia_percentual']);
    $formData['valor_implantacao'] = $_POST['valor_implantacao'] === '' ? null : (float)str_replace(',', '.', $_POST['valor_implantacao']);
    $formData['valor_mensal'] = $_POST['valor_mensal'] === '' ? null : (float)str_replace(',', '.', $_POST['valor_mensal']);
    $formData['descricao'] = trim($_POST['descricao'] ?? '');
    $formData['observacoes'] = trim($_POST['observacoes'] ?? '');
    $formData['data_envio'] = trim($_POST['data_envio'] ?? '');
    $formData['validade_dias'] = $_POST['validade_dias'] === '' ? '' : (int)$_POST['validade_dias'];
    $formData['status'] = in_array($_POST['status'] ?? '', $allowedStatus, true) ? $_POST['status'] : 'rascunho';

    // Validacao basica dos dados gerais
    if ($formData['id_cliente'] <= 0) {
        $errors[] = 'Selecione um cliente valido.';
    } else {
        $clienteExiste = run_query('SELECT id FROM clientes WHERE id = ?', [$formData['id_cliente']]);
        if (!$clienteExiste) {
            $errors[] = 'O cliente informado nao foi encontrado.';
        }
    }

    if ($formData['id_pacote']) {
        $pacoteExiste = run_query('SELECT id FROM pacotes WHERE id = ?', [$formData['id_pacote']]);
        if (!$pacoteExiste) {
            $errors[] = 'O pacote selecionado nao e valido.';
        }
    }

    if ($formData['descricao'] === '') {
        $errors[] = 'Informe uma descricao para a proposta.';
    }

    $dataEnvioDb = null;
    if ($formData['data_envio'] !== '') {
        $parse = str_replace('T', ' ', $formData['data_envio']);
        $timestamp = strtotime($parse);
        if ($timestamp === false) {
            $errors[] = 'Informe uma data de envio valida.';
        } else {
            $dataEnvioDb = date('Y-m-d H:i:s', $timestamp);
        }
    }

    $validadeDias = $formData['validade_dias'] === '' ? null : (int)$formData['validade_dias'];
    if ($validadeDias !== null && $validadeDias < 0) {
        $errors[] = 'A validade deve ser um numero positivo de dias.';
    }

    if ($formData['status'] === 'aceita') {
        if ($dataEnvioDb === null) {
            $errors[] = 'Propostas aceitas precisam da data de envio preenchida.';
        }
        if ($validadeDias === null || $validadeDias <= 0) {
            $errors[] = 'Propostas aceitas precisam de validade em dias maior que zero.';
        }
    }

    // Processa itens
    $postedItems = $_POST['items'] ?? [];
    $parsedItems = [];
    if (is_array($postedItems)) {
        foreach ($postedItems as $item) {
            $descricaoItem = trim($item['descricao_item'] ?? '');
            $tipoItem = in_array($item['tipo_item'] ?? 'servico', ['servico', 'material'], true)
                ? $item['tipo_item']
                : 'servico';
            $quantidade = isset($item['quantidade']) ? (float)$item['quantidade'] : 0.0;
            $valorUnitario = isset($item['valor_unitario']) ? (float)$item['valor_unitario'] : 0.0;

            if ($descricaoItem === '' && $quantidade <= 0 && $valorUnitario <= 0) {
                continue; // ignora linhas em branco
            }

            if ($descricaoItem === '') {
                $errors[] = 'Informe a descricao de todos os itens adicionados.';
                break;
            }

            if ($quantidade <= 0) {
                $errors[] = 'Itens precisam ter quantidade maior que zero.';
                break;
            }

            if ($valorUnitario < 0) {
                $errors[] = 'Itens nao podem ter valor unitario negativo.';
                break;
            }

            $valorTotal = round($quantidade * $valorUnitario, 2);

            $parsedItems[] = [
                'tipo_item' => $tipoItem,
                'descricao_item' => $descricaoItem,
                'quantidade' => round($quantidade, 2),
                'valor_unitario' => round($valorUnitario, 2),
                'valor_total' => $valorTotal,
            ];
        }
    }

    if (!$errors && count($parsedItems) === 0) {
        $errors[] = 'Adicione pelo menos um item a proposta.';
    }

    $itensForm = $parsedItems ?: $itensForm;

    $totalServicos = 0.0;
    $totalMateriais = 0.0;
    foreach ($parsedItems as $item) {
        if ($item['tipo_item'] === 'material') {
            $totalMateriais += $item['valor_total'];
        } else {
            $totalServicos += $item['valor_total'];
        }
    }
    $totalServicos = round($totalServicos, 2);
    $totalMateriais = round($totalMateriais, 2);
    $totalGeral = round($totalServicos + $totalMateriais, 2);

    if (!$errors) {
        $pdo = pdo();
        try {
            $pdo->beginTransaction();

            $numeroColaboradores = $formData['numero_colaboradores'];
            $sinPercentual = $formData['sinistralidade_percentual'];
            $franquiaPercentual = $formData['franquia_percentual'];
            $valorImplantacao = $formData['valor_implantacao'];
            $valorMensal = $formData['valor_mensal'];

            if ($sinPercentual !== null) {
                $sinPercentual = round($sinPercentual, 2);
            }
            if ($franquiaPercentual !== null) {
                $franquiaPercentual = round($franquiaPercentual, 2);
            }
            if ($valorImplantacao !== null) {
                $valorImplantacao = round($valorImplantacao, 2);
            }
            if ($valorMensal !== null) {
                $valorMensal = round($valorMensal, 2);
            }

            $dataToPersist = [
                $formData['id_cliente'],
                $formData['id_pacote'],
                $formData['modelo_id'],
                $numeroColaboradores,
                $sinPercentual,
                $franquiaPercentual,
                $valorImplantacao,
                $valorMensal,
                current_user()['id'] ?? null,
                $formData['descricao'],
                $formData['observacoes'] ?: null,
                $dataEnvioDb,
                $validadeDias,
                $formData['status'],
                $totalServicos,
                $totalMateriais,
                $totalGeral,
            ];

            if ($editando) {
                $stmt = $pdo->prepare(
                    'UPDATE propostas
                     SET id_cliente = ?, id_pacote = ?, modelo_id = ?, numero_colaboradores = ?, sinistralidade_percentual = ?, franquia_percentual = ?, valor_implantacao = ?, valor_mensal = ?, id_usuario = ?, descricao = ?, observacoes = ?, data_envio = ?, validade_dias = ?, status = ?,
                         total_servicos = ?, total_materiais = ?, total_geral = ?
                     WHERE id = ?'
                );
                $stmt->execute(array_merge($dataToPersist, [$propostaId]));

                $pdo->prepare('DELETE FROM proposta_itens WHERE id_proposta = ?')->execute([$propostaId]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO propostas (codigo_proposta, id_cliente, id_pacote, modelo_id, numero_colaboradores, sinistralidade_percentual, franquia_percentual, valor_implantacao, valor_mensal, id_usuario, descricao, observacoes, data_envio, validade_dias, status, total_servicos, total_materiais, total_geral)
                     VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute($dataToPersist);
                $propostaId = (int)$pdo->lastInsertId();

                $codigo = 'PROP-' . str_pad((string)$propostaId, 4, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE propostas SET codigo_proposta = ? WHERE id = ? AND codigo_proposta IS NULL')->execute([$codigo, $propostaId]);
            }

            $stmtItem = $pdo->prepare(
                'INSERT INTO proposta_itens (id_proposta, tipo_item, descricao_item, quantidade, valor_unitario, valor_total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($parsedItems as $item) {
                $stmtItem->execute([
                    $propostaId,
                    $item['tipo_item'],
                    $item['descricao_item'],
                    $item['quantidade'],
                    $item['valor_unitario'],
                    $item['valor_total'],
                ]);
            }

            $pdo->commit();

            $logAntes = $editando ? array_merge($propostaAtual ?? [], ['itens' => $itensAtuais]) : null;
            $logDepois = [
                'id_cliente' => $formData['id_cliente'],
                'id_pacote' => $formData['id_pacote'],
                'numero_colaboradores' => $numeroColaboradores,
                'sinistralidade_percentual' => $sinPercentual,
                'franquia_percentual' => $franquiaPercentual,
                'valor_implantacao' => $valorImplantacao,
                'valor_mensal' => $valorMensal,
                'descricao' => $formData['descricao'],
                'status' => $formData['status'],
                'data_envio' => $dataEnvioDb,
                'validade_dias' => $validadeDias,
                'totais' => [
                    'servicos' => $totalServicos,
                    'materiais' => $totalMateriais,
                    'geral' => $totalGeral,
                ],
                'itens' => $parsedItems,
            ];

            log_user_action(
                current_user()['id'] ?? null,
                $editando ? 'Atualizou proposta' : 'Criou proposta',
                'propostas',
                $propostaId,
                $logAntes,
                $logDepois
            );

            redirect(app_url('propostas/ver.php?id=' . $propostaId));
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_system('error', 'Falha ao salvar proposta: ' . $e->getMessage(), __FILE__, __LINE__, $e->getTraceAsString());
            $errors[] = 'Falha ao salvar a proposta. Tente novamente.';
        }
    }
}

// Totais iniciais para exibicao
$totalServicosInicial = 0.0;
$totalMateriaisInicial = 0.0;
foreach ($itensForm as $item) {
    if (($item['tipo_item'] ?? 'servico') === 'material') {
        $totalMateriaisInicial += (float)($item['valor_total'] ?? 0);
    } else {
        $totalServicosInicial += (float)($item['valor_total'] ?? 0);
    }
}
$totalServicosInicial = round($totalServicosInicial, 2);
$totalMateriaisInicial = round($totalMateriaisInicial, 2);
$totalGeralInicial = round($totalServicosInicial + $totalMateriaisInicial, 2);

$page_title = $editando ? 'Editar Proposta' : 'Nova Proposta';
$breadcrumb = 'Comercial > Propostas > ' . ($editando ? 'Editar' : 'Nova');

$itemsJson = json_encode($itensForm, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

$pacotesMapForJs = [];
foreach ($pacotes as $pacoteRow) {
    $pacotesMapForJs[(int)$pacoteRow['id']] = [
        'id' => (int)$pacoteRow['id'],
        'tipo_calculo' => $pacoteRow['tipo_calculo'] ?? null,
        'valor_implantacao_base' => isset($pacoteRow['valor_implantacao_base']) ? (float)$pacoteRow['valor_implantacao_base'] : 0.0,
        'valor_mensal_base' => isset($pacoteRow['valor_mensal_base']) ? (float)$pacoteRow['valor_mensal_base'] : 0.0,
        'sinistralidade_padrao' => isset($pacoteRow['sinistralidade_padrao']) ? (float)$pacoteRow['sinistralidade_padrao'] : null,
        'franquia_padrao' => isset($pacoteRow['franquia_padrao']) ? (float)$pacoteRow['franquia_padrao'] : null,
    ];
}

$pacoteSelecionado = null;
if (!empty($formData['id_pacote'])) {
    foreach ($pacotes as $pacoteDados) {
        if ((int)($pacoteDados['id'] ?? 0) === (int)$formData['id_pacote']) {
            $pacoteSelecionado = $pacoteDados;
            break;
        }
    }
}

ob_start();
?>
<div class="card shadow-sm mb-3">
  <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h5 class="fw-bold text-primary mb-0">
      <?= e($page_title) ?>
    </h5>
    <div class="d-flex gap-2">
      <a href="<?= e(app_url('propostas/listar.php')) ?>" class="btn btn-outline-secondary btn-sm">&laquo; Voltar</a>
      <?php if ($editando): ?>
      <a href="<?= e(app_url('propostas/ver.php?id=' . $propostaId)) ?>" class="btn btn-primary btn-sm">Ver proposta</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <div class="fw-bold mb-2">Nao foi possivel salvar a proposta:</div>
    <ul class="mb-0">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST" id="proposta-form" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$formData['id'] ?>">

  <div class="d-flex gap-2 mb-3">
    <span class="badge bg-primary" data-step-indicator="1">1. Dados gerais</span>
    <span class="badge bg-light text-dark" data-step-indicator="2">2. Itens da proposta</span>
  </div>

  <div data-step="1" class="step-pane">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Cliente *</label>
            <select name="id_cliente" class="form-select" required>
              <option value="">Selecione...</option>
              <?php foreach ($clientes as $cli): ?>
                <option value="<?= (int)$cli['id'] ?>" <?= (int)$formData['id_cliente'] === (int)$cli['id'] ? 'selected' : '' ?>>
                  <?= e($cli['nome_fantasia']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Pacote (opcional)</label>
            <select name="id_pacote" class="form-select">
              <option value="">Nenhum</option>
              <?php foreach ($pacotes as $pac): ?>
                <option value="<?= (int)$pac['id'] ?>" <?= (int)($formData['id_pacote'] ?? 0) === (int)$pac['id'] ? 'selected' : '' ?>>
                  <?= e($pac['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Modelo de Documento</label>
            <select name="modelo_id" class="form-select">
              <option value="">Sem modelo (PDF padrao)</option>
              <?php 
              $currentCategoria = '';
              foreach ($modelos as $mod): 
                if ($currentCategoria !== $mod['categoria']):
                  if ($currentCategoria !== '') echo '</optgroup>';
                  $currentCategoria = $mod['categoria'];
                  echo '<optgroup label="' . e($currentCategoria) . '">';
                endif;
              ?>
                <option value="<?= (int)$mod['id'] ?>" <?= (int)($formData['modelo_id'] ?? 0) === (int)$mod['id'] ? 'selected' : '' ?>>
                  <?= e($mod['titulo']) ?>
                </option>
              <?php endforeach; ?>
              <?php if ($currentCategoria !== '') echo '</optgroup>'; ?>
            </select>
            <small class="text-muted">Escolha um modelo para gerar o PDF da proposta</small>
          </div>
          <div class="col-12">
            <div id="pacote-info" class="alert alert-info small text-dark mb-0">
              <div id="pacote-info-title" class="fw-semibold"><?= e($pacoteSelecionado['nome'] ?? 'Nenhum pacote selecionado') ?></div>
              <div id="pacote-info-body" class="mt-1">
                <?php if ($pacoteSelecionado): ?>
                  <?php if (!empty($pacoteSelecionado['descricao'])): ?>
                    <div><?= nl2br(e((string)($pacoteSelecionado['descricao'] ?? ''))) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($pacoteSelecionado['conformidade'])): ?>
                    <div class="mt-1"><small class="text-muted">Conformidade:</small> <?= nl2br(e((string)($pacoteSelecionado['conformidade'] ?? ''))) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <div>Selecione um pacote para visualizar os parametros.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Numero de colaboradores</label>
            <input type="number" name="numero_colaboradores" min="0" step="1" class="form-control"
                   value="<?= $formData['numero_colaboradores'] !== null ? e((string)$formData['numero_colaboradores']) : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Sinistralidade (%)</label>
            <input type="number" name="sinistralidade_percentual" min="0" step="0.01" class="form-control"
                   value="<?= $formData['sinistralidade_percentual'] !== null ? e(number_format((float)$formData['sinistralidade_percentual'], 2, '.', '')) : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Franquia (%)</label>
            <input type="number" name="franquia_percentual" min="0" step="0.01" class="form-control"
                   value="<?= $formData['franquia_percentual'] !== null ? e(number_format((float)$formData['franquia_percentual'], 2, '.', '')) : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Implantacao (R$)</label>
            <input type="number" name="valor_implantacao" min="0" step="0.01" class="form-control"
                   value="<?= $formData['valor_implantacao'] !== null ? e(number_format((float)$formData['valor_implantacao'], 2, '.', '')) : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Mensalidade (R$)</label>
            <input type="number" name="valor_mensal" min="0" step="0.01" class="form-control"
                   value="<?= $formData['valor_mensal'] !== null ? e(number_format((float)$formData['valor_mensal'], 2, '.', '')) : '' ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Descricao *</label>
            <textarea name="descricao" class="form-control" rows="3" required><?= e($formData['descricao']) ?></textarea>
          </div>
          <div class="col-md-12">
            <label class="form-label">Observacoes</label>
            <textarea name="observacoes" class="form-control" rows="3"><?= e($formData['observacoes']) ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach ($allowedStatus as $status): ?>
                <option value="<?= e($status) ?>" <?= $formData['status'] === $status ? 'selected' : '' ?>>
                  <?= e(ucfirst($status)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data de envio<?= $formData['status'] === 'aceita' ? ' *' : '' ?></label>
            <input type="datetime-local" name="data_envio" value="<?= e($formData['data_envio']) ?>" class="form-control">
            <small class="text-muted">Obrigatorio quando o status for "aceita".</small>
          </div>
          <div class="col-md-4">
            <label class="form-label">Validade (dias)</label>
            <input type="number" name="validade_dias" value="<?= e($formData['validade_dias']) ?>" class="form-control" min="0" step="1">
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light">
          <div class="text-muted small">Total em servicos</div>
          <div class="fs-5 fw-semibold" data-total="servicos">R$ <?= number_format($totalServicosInicial, 2, ',', '.') ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light">
          <div class="text-muted small">Total em materiais</div>
          <div class="fs-5 fw-semibold" data-total="materiais">R$ <?= number_format($totalMateriaisInicial, 2, ',', '.') ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light">
          <div class="text-muted small">Valor total da proposta</div>
          <div class="fs-5 fw-semibold text-primary" data-total="geral">R$ <?= number_format($totalGeralInicial, 2, ',', '.') ?></div>
        </div>
      </div>
    </div>
  </div>

  <div data-step="2" class="step-pane d-none">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h6 class="fw-bold mb-0">Itens da proposta</h6>
            <small class="text-muted">Adicione servicos e materiais. O total e calculado automaticamente.</small>
          </div>
          <button type="button" class="btn btn-success btn-sm" id="add-item">+ Adicionar item</button>
        </div>

        <div class="table-responsive">
          <table class="table table-striped table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 12%">Tipo</th>
                <th>Descricao</th>
                <th style="width: 12%">Qtd.</th>
                <th style="width: 16%">Valor unitario (R$)</th>
                <th style="width: 16%">Total (R$)</th>
                <th style="width: 8%" class="text-center">&nbsp;</th>
              </tr>
            </thead>
            <tbody id="items-body"></tbody>
          </table>
        </div>

        <div id="items-empty" class="alert alert-info mt-3<?= $itensForm ? ' d-none' : '' ?>">
          Nenhum item adicionado ainda. Use o botao "Adicionar item" para comecar.
        </div>
        <div id="items-error" class="alert alert-danger mt-3 d-none"></div>

        <div class="row g-3 mt-3">
          <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
              <div class="text-muted small">Total em servicos</div>
              <div class="fs-6 fw-semibold" data-total="servicos">R$ <?= number_format($totalServicosInicial, 2, ',', '.') ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
              <div class="text-muted small">Total em materiais</div>
              <div class="fs-6 fw-semibold" data-total="materiais">R$ <?= number_format($totalMateriaisInicial, 2, ',', '.') ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
              <div class="text-muted small">Valor total da proposta</div>
              <div class="fs-5 fw-semibold text-primary" data-total="geral">R$ <?= number_format($totalGeralInicial, 2, ',', '.') ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-4">
    <button type="button" class="btn btn-outline-secondary" id="btn-prev">Anterior</button>
    <div class="ms-auto d-flex gap-2">
      <button type="button" class="btn btn-primary" id="btn-next">Proximo</button>
      <button type="submit" class="btn btn-success d-none" id="btn-submit"><?= $editando ? 'Atualizar Proposta' : 'Salvar Proposta' ?></button>
    </div>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('proposta-form');
  const steps = Array.from(document.querySelectorAll('[data-step]'));
  const indicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
  const btnPrev = document.getElementById('btn-prev');
  const btnNext = document.getElementById('btn-next');
  const btnSubmit = document.getElementById('btn-submit');
  const addItemBtn = document.getElementById('add-item');
  const tbody = document.getElementById('items-body');
  const emptyAlert = document.getElementById('items-empty');
  const errorAlert = document.getElementById('items-error');
  const totalsMap = {
    servicos: Array.from(document.querySelectorAll('[data-total="servicos"]')),
    materiais: Array.from(document.querySelectorAll('[data-total="materiais"]')),
    geral: Array.from(document.querySelectorAll('[data-total="geral"]')),
  };

  let currentStep = 1;
  let rowCount = 0;

  const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  const pacotesConfig = <?= json_encode($pacotesMapForJs, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?> || {};
  const isEditing = <?= $editando ? 'true' : 'false' ?>;
  const selectPacote = document.querySelector('select[name="id_pacote"]');
  const colaboradoresInput = document.querySelector('input[name="numero_colaboradores"]');
  const sinistralidadeInput = document.querySelector('input[name="sinistralidade_percentual"]');
  const franquiaInput = document.querySelector('input[name="franquia_percentual"]');
  const valorImplantacaoInput = document.querySelector('input[name="valor_implantacao"]');
  const valorMensalInput = document.querySelector('input[name="valor_mensal"]');
  const pacoteInfoTitle = document.getElementById('pacote-info-title');
  const pacoteInfoBody = document.getElementById('pacote-info-body');
  let manualValorImplantacao = valorImplantacaoInput ? valorImplantacaoInput.value.trim() !== '' : false;
  let manualValorMensal = valorMensalInput ? valorMensalInput.value.trim() !== '' : false;

  function formatNumberToField(value) {
    return (Math.round(value * 100) / 100).toFixed(2);
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
      return map[char] || char;
    });
  }

  function renderMultiline(value) {
    return escapeHtml(value).replace(/\r?\n/g, '<br>');
  }

  function atualizarResumoPacote(pacote) {
    if (!pacoteInfoTitle || !pacoteInfoBody) {
      return;
    }

    if (!pacote) {
      pacoteInfoTitle.textContent = 'Nenhum pacote selecionado';
      pacoteInfoBody.innerHTML = '<div>Selecione um pacote para visualizar os parametros.</div>';
      return;
    }

    pacoteInfoTitle.textContent = pacote.nome || 'Nenhum pacote selecionado';

    const partes = [];
    if (pacote.descricao) {
      partes.push('<div>' + renderMultiline(pacote.descricao) + '</div>');
    }
    if (pacote.conformidade) {
      partes.push('<div class="mt-1"><small class="text-muted">Conformidade:</small> ' + renderMultiline(pacote.conformidade) + '</div>');
    }
    if (!partes.length) {
      partes.push('<div>Selecione um pacote para visualizar os parametros.</div>');
    }
    pacoteInfoBody.innerHTML = partes.join('');
  }

  function aplicarParametrosPacote(forceOverride) {
    if (!selectPacote) {
      return;
    }

    const pacoteId = parseInt(selectPacote.value, 10);
    const pacote = Number.isInteger(pacoteId) ? pacotesConfig[pacoteId] : null;

    atualizarResumoPacote(pacote || null);

    if (!pacote) {
      if (forceOverride) {
        if (valorImplantacaoInput && !manualValorImplantacao) {
          valorImplantacaoInput.value = '';
        }
        if (valorMensalInput && !manualValorMensal) {
          valorMensalInput.value = '';
        }
        if (sinistralidadeInput) {
          sinistralidadeInput.value = '';
        }
        if (franquiaInput) {
          franquiaInput.value = '';
        }
      }
      return;
    }

    const colaboradores = colaboradoresInput ? parseInt(colaboradoresInput.value, 10) : NaN;

    if (sinistralidadeInput && (forceOverride || !sinistralidadeInput.value)) {
      const sinPadrao = parseFloat(pacote.sinistralidade_padrao);
      if (Number.isFinite(sinPadrao)) {
        let sinFinal = sinPadrao;
        if (Number.isFinite(colaboradores) && colaboradores >= 300 && (pacote.tipo_calculo === 'sinistralidade' || pacote.tipo_calculo === 'franquia')) {
          sinFinal = Math.min(sinFinal, 5);
        }
        sinistralidadeInput.value = formatNumberToField(sinFinal);
      } else if (forceOverride) {
        sinistralidadeInput.value = '';
      }
    }

    if (franquiaInput && (forceOverride || !franquiaInput.value)) {
      const franqPadrao = parseFloat(pacote.franquia_padrao);
      if (Number.isFinite(franqPadrao)) {
        let franqFinal = franqPadrao;
        if (Number.isFinite(colaboradores) && colaboradores >= 300 && pacote.tipo_calculo === 'franquia') {
          franqFinal = Math.min(franqFinal, 5);
        }
        franquiaInput.value = formatNumberToField(franqFinal);
      } else if (forceOverride) {
        franquiaInput.value = '';
      }
    }

    if (!Number.isFinite(colaboradores) || colaboradores <= 0) {
      if (forceOverride) {
        if (valorImplantacaoInput && !manualValorImplantacao) {
          valorImplantacaoInput.value = '';
        }
        if (valorMensalInput && !manualValorMensal) {
          valorMensalInput.value = '';
        }
      }
      return;
    }

    const baseImplantacao = parseFloat(pacote.valor_implantacao_base);
    const baseMensal = parseFloat(pacote.valor_mensal_base);

    let implCalc = Number.isFinite(baseImplantacao) ? baseImplantacao : null;
    let mensalCalc = Number.isFinite(baseMensal) ? baseMensal : null;

    if (pacote.tipo_calculo === 'fixo') {
      if (Number.isFinite(baseImplantacao) && baseImplantacao > 0) {
        implCalc = Math.max(1, Math.ceil(colaboradores / 50)) * baseImplantacao;
      } else {
        implCalc = null;
      }
      mensalCalc = 0;
    } else {
      if (Number.isFinite(baseMensal)) {
        mensalCalc = colaboradores * baseMensal;
      }
    }

    if (valorImplantacaoInput) {
      if (Number.isFinite(implCalc) && (!manualValorImplantacao || forceOverride)) {
        valorImplantacaoInput.value = formatNumberToField(implCalc);
        manualValorImplantacao = false;
      } else if (forceOverride && !manualValorImplantacao) {
        valorImplantacaoInput.value = '';
      }
    }

    if (valorMensalInput) {
      if (Number.isFinite(mensalCalc) && (!manualValorMensal || forceOverride)) {
        valorMensalInput.value = formatNumberToField(mensalCalc);
        manualValorMensal = false;
      } else if (forceOverride && !manualValorMensal) {
        valorMensalInput.value = '';
      }
    }
  }

  if (valorImplantacaoInput) {
    valorImplantacaoInput.addEventListener('input', function () {
      manualValorImplantacao = this.value.trim() !== '';
    });
  }

  if (valorMensalInput) {
    valorMensalInput.addEventListener('input', function () {
      manualValorMensal = this.value.trim() !== '';
    });
  }

  if (selectPacote) {
    selectPacote.addEventListener('change', function () {
      manualValorImplantacao = valorImplantacaoInput ? valorImplantacaoInput.value.trim() !== '' : false;
      manualValorMensal = valorMensalInput ? valorMensalInput.value.trim() !== '' : false;
      aplicarParametrosPacote(true);
    });
  }

  if (colaboradoresInput) {
    colaboradoresInput.addEventListener('input', function () {
      aplicarParametrosPacote(false);
    });
  }

  aplicarParametrosPacote(!isEditing);
  function showStep(step) {
    currentStep = step;
    steps.forEach(function (pane) {
      pane.classList.toggle('d-none', Number(pane.dataset.step) !== step);
    });
    indicators.forEach(function (badge) {
      const isActive = Number(badge.dataset.stepIndicator) === step;
      badge.classList.toggle('bg-primary', isActive);
      badge.classList.toggle('bg-light', !isActive);
      badge.classList.toggle('text-dark', !isActive);
    });
    btnPrev.classList.toggle('d-none', step === 1);
    btnNext.classList.toggle('d-none', step !== 1);
    btnSubmit.classList.toggle('d-none', step !== 2);
  }

  function syncItemNames() {
    Array.from(tbody.querySelectorAll('tr')).forEach(function (row, index) {
      Array.from(row.querySelectorAll('[data-name]')).forEach(function (input) {
        const field = input.getAttribute('data-name');
        input.name = 'items[' + index + '][' + field + ']';
      });
    });
  }

  function ensureEmptyState() {
    const hasRows = tbody.querySelectorAll('tr').length > 0;
    emptyAlert.classList.toggle('d-none', hasRows);
  }

  function formatCurrency(value) {
    return currencyFormatter.format(Number.isFinite(value) ? value : 0);
  }

  function syncTotals() {
    let totalServicos = 0;
    let totalMateriais = 0;

    Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
      const tipo = row.querySelector('.item-tipo').value;
      const total = parseFloat(row.querySelector('[data-name="valor_total"]').value) || 0;
      if (tipo === 'material') {
        totalMateriais += total;
      } else {
        totalServicos += total;
      }
    });

    const totalGeral = totalServicos + totalMateriais;

    totalsMap.servicos.forEach(function (el) { el.textContent = formatCurrency(totalServicos); });
    totalsMap.materiais.forEach(function (el) { el.textContent = formatCurrency(totalMateriais); });
    totalsMap.geral.forEach(function (el) { el.textContent = formatCurrency(totalGeral); });
  }

  function updateRowTotal(row) {
    const quantidade = parseFloat(row.querySelector('.item-quantidade').value) || 0;
    const unitario = parseFloat(row.querySelector('.item-unitario').value) || 0;
    const total = Math.max(0, quantidade * unitario);

    row.querySelector('[data-name="valor_total"]').value = total.toFixed(2);
    row.querySelector('.item-total').textContent = formatCurrency(total);

    syncTotals();
  }

  function addItemRow(data) {
    const defaults = {
      tipo_item: 'servico',
      descricao_item: '',
      quantidade: 1,
      valor_unitario: 0,
      valor_total: 0,
    };
    const item = Object.assign({}, defaults, data || {});

    const tr = document.createElement('tr');
    tr.dataset.row = String(rowCount++);
    tr.innerHTML = `
      <td>
        <select class="form-select form-select-sm item-tipo" data-name="tipo_item">
          <option value="servico" ${item.tipo_item === 'servico' ? 'selected' : ''}>Servico</option>
          <option value="material" ${item.tipo_item === 'material' ? 'selected' : ''}>Material</option>
        </select>
      </td>
      <td>
        <input type="text" class="form-control form-control-sm item-descricao" data-name="descricao_item" value="">
      </td>
      <td>
        <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end item-quantidade" data-name="quantidade" value="1">
      </td>
      <td>
        <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end item-unitario" data-name="valor_unitario" value="0">
      </td>
      <td class="text-end">
        <span class="item-total">${formatCurrency(item.valor_total)}</span>
        <input type="hidden" data-name="valor_total" value="0.00">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-outline-danger btn-sm item-remove" title="Remover item">&times;</button>
      </td>
    `;

    tbody.appendChild(tr);

    const selectTipo = tr.querySelector('.item-tipo');
    const inputDescricao = tr.querySelector('.item-descricao');
    const inputQuantidade = tr.querySelector('.item-quantidade');
    const inputUnitario = tr.querySelector('.item-unitario');

    selectTipo.value = item.tipo_item;
    inputDescricao.value = item.descricao_item || '';
    inputQuantidade.value = item.quantidade;
    inputUnitario.value = item.valor_unitario;

    inputQuantidade.addEventListener('input', function () { updateRowTotal(tr); });
    inputUnitario.addEventListener('input', function () { updateRowTotal(tr); });
    selectTipo.addEventListener('change', function () { syncTotals(); });
    tr.querySelector('.item-remove').addEventListener('click', function () {
      tr.remove();
      syncItemNames();
      ensureEmptyState();
      syncTotals();
    });

    syncItemNames();
    ensureEmptyState();
    updateRowTotal(tr);
  }

  btnPrev.addEventListener('click', function () {
    if (currentStep > 1) {
      showStep(currentStep - 1);
    }
  });

  btnNext.addEventListener('click', function () {
    if (!form.reportValidity()) {
      return;
    }
    showStep(2);
  });

  addItemBtn.addEventListener('click', function () {
    addItemRow();
  });

  form.addEventListener('submit', function (event) {
    syncItemNames();

    const hasValidItem = Array.from(tbody.querySelectorAll('tr')).some(function (row) {
      const descricao = (row.querySelector('.item-descricao').value || '').trim();
      const quantidade = parseFloat(row.querySelector('.item-quantidade').value) || 0;
      const unitario = parseFloat(row.querySelector('.item-unitario').value) || 0;
      return descricao !== '' && quantidade > 0 && unitario >= 0;
    });

    if (!hasValidItem) {
      event.preventDefault();
      errorAlert.textContent = 'Adicione pelo menos um item valido (com descricao e quantidade).';
      errorAlert.classList.remove('d-none');
      showStep(2);
      return;
    }

    errorAlert.classList.add('d-none');
  });

  // Carrega itens iniciais
  const initialItems = <?= $itemsJson ?: '[]' ?>;

  if (Array.isArray(initialItems) && initialItems.length > 0) {
    initialItems.forEach(function (item) { addItemRow(item); });
  } else {
    ensureEmptyState();
  }

  showStep(currentStep);
  syncTotals();
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
