<?php
require_once 'BaseController.php';

class DashboardController extends BaseController {
    public function index() {
        ensure_session_security();
        $user = current_user();

        $totalClientes = (int)(run_query("SELECT COUNT(*) AS c FROM clientes")[0]['c'] ?? 0);
        $totalPropostas = (int)(run_query("SELECT COUNT(*) AS c FROM propostas")[0]['c'] ?? 0);
        $totalPacotes = (int)(run_query("SELECT COUNT(*) AS c FROM pacotes WHERE ativo=TRUE")[0]['c'] ?? 0);
        $totalUsuarios = (int)(run_query("SELECT COUNT(*) AS c FROM usuarios WHERE ativo=TRUE")[0]['c'] ?? 0);

        $this->view('dashboard/index', [
            'user' => $user,
            'totalClientes' => $totalClientes,
            'totalPropostas' => $totalPropostas,
            'totalPacotes' => $totalPacotes,
            'totalUsuarios' => $totalUsuarios
        ]);
    }
}
