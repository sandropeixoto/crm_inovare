<?php
require_once __DIR__ . '/../../config/db.php';
ensure_session_security();
require_role(['admin','gestor']);

$page_title = "Relatório Financeiro";
$breadcrumb = "Relatórios > Financeiro";
$quick_action = ['url'=>'../propostas/listar.php','label'=>'Ver Propostas'];

/* ==========================
   TOTAIS POR STATUS
========================== */
$dados = run_query("
    SELECT status,
           COUNT(*) AS total_propostas,
           SUM(total_geral) AS soma
    FROM propostas
    GROUP BY status
");

$map = [];
if (is_array($dados)) {
    foreach ($dados as $d) {
        $status = $d['status'] ?? 'indefinido';
        $map[$status] = [
            'total' => (int)($d['total_propostas'] ?? 0),
            'valor' => (float)($d['soma'] ?? 0)
        ];
    }
}

$labels = ["aceita","enviada","rejeitada","rascunho","expirada"];
$cores = [
    "aceita" => "#198754",
    "enviada" => "#0d6efd",
    "rejeitada" => "#dc3545",
    "rascunho" => "#ffc107",
    "expirada" => "#6c757d"
];

/* ==========================
   GRÁFICO TEMPORAL
========================== */
$dados_mensais = run_query("
    SELECT strftime('%Y-%m', criado_em) AS mes,
           COUNT(*) AS total,
           SUM(total_geral) AS soma
    FROM propostas
    WHERE DATE(criado_em) >= DATE('now', '-12 months')
    GROUP BY mes
    ORDER BY mes ASC
");

$meses = [];
$totais_mes = [];
$valores_mes = [];

if (is_array($dados_mensais)) {
    foreach ($dados_mensais as $d) {
        $mes_str = $d['mes'] ?? null;
        if (!$mes_str) continue;
        $mes_pt = date('m/Y', strtotime($mes_str . '-01'));
        $meses[] = $mes_pt;
        $totais_mes[] = (int)($d['total'] ?? 0);
        $valores_mes[] = (float)($d['soma'] ?? 0);
    }
}

/* ==========================
   CÁLCULOS CONSOLIDADOS
========================== */
$total_propostas = array_sum(array_column($map, 'total'));
$total_valor = array_sum(array_column($map, 'valor'));

ob_start();
?>
<div class="row">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h6 class="fw-bold text-primary mb-3">Valores Totais por Status</h6>
        <canvas id="graficoFinanceiro" height="140"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h6 class="fw-bold text-primary mb-3">Resumo</h6>
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Status</th>
              <th>Qtde</th>
              <th>Total (R$)</th>
              <th>%</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($labels as $s): 
            $total = $map[$s]['total'] ?? 0;
            $valor = $map[$s]['valor'] ?? 0;
            $percent = $total_propostas > 0 ? round(($total / $total_propostas) * 100, 1) : 0;
          ?>
            <tr>
              <td><span class="badge" style="background:<?= $cores[$s] ?>"><?= ucfirst($s) ?></span></td>
              <td><?= $total ?></td>
              <td><?= number_format($valor,2,',','.') ?></td>
              <td><?= $percent ?>%</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td>Total</td>
              <td><?= $total_propostas ?></td>
              <td>R$ <?= number_format($total_valor, 2, ',', '.') ?></td>
              <td>100%</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- GRÁFICO TEMPORAL -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <h6 class="fw-bold text-primary mb-3">Evolução Mensal das Propostas</h6>
    <canvas id="graficoTemporal" height="120"></canvas>
  </div>
</div>

<!-- TOTAIS -->
<div class="card shadow-sm">
  <div class="card-body">
    <h6 class="fw-bold text-primary mb-3">Totais Consolidados</h6>
    <p>Total geral de propostas: <strong><?= $total_propostas ?></strong></p>
    <p>Valor total movimentado:
      <strong>R$ <?= number_format($total_valor, 2, ',', '.') ?></strong>
    </p>
  </div>
</div>

<!-- CHARTS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  // === GRÁFICO DE BARRAS POR STATUS ===
  const ctx1 = document.getElementById("graficoFinanceiro").getContext("2d");
  new Chart(ctx1, {
    type: "bar",
    data: {
      labels: <?= json_encode(array_map('ucfirst', $labels)) ?>,
      datasets: [{
        label: "Valor total (R$)",
        data: <?= json_encode(array_map(fn($s)=>round($map[$s]['valor']??0,2), $labels)) ?>,
        backgroundColor: <?= json_encode(array_values($cores)) ?>
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              let val = ctx.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
              return ' ' + val;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }
          }
        }
      }
    }
  });

  // === GRÁFICO TEMPORAL (LINHA) ===
  const ctx2 = document.getElementById("graficoTemporal").getContext("2d");
  new Chart(ctx2, {
    type: "line",
    data: {
      labels: <?= json_encode($meses) ?>,
      datasets: [
        {
          label: "Valor Total (R$)",
          data: <?= json_encode($valores_mes) ?>,
          borderColor: "#0d6efd",
          backgroundColor: "rgba(13,110,253,0.1)",
          tension: 0.3,
          yAxisID: 'y1'
        },
        {
          label: "Quantidade de Propostas",
          data: <?= json_encode($totais_mes) ?>,
          borderColor: "#198754",
          backgroundColor: "rgba(25,135,84,0.2)",
          tension: 0.3,
          yAxisID: 'y2'
        }
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      stacked: false,
      plugins: {
        tooltip: {
          callbacks: {
            label: function(ctx) {
              if (ctx.dataset.label.includes("Valor"))
                return ctx.dataset.label + ": " + ctx.parsed.y.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
              return ctx.dataset.label + ": " + ctx.parsed.y;
            }
          }
        }
      },
      scales: {
        y1: {
          type: 'linear',
          position: 'left',
          ticks: {
            callback: function(value) {
              return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }
          }
        },
        y2: {
          type: 'linear',
          position: 'right',
          grid: { drawOnChartArea: false },
          ticks: {
            callback: function(value) { return value; }
          }
        }
      }
    }
  });
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../inc/template_base.php';
