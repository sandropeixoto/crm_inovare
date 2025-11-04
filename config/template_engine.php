<?php

class TemplateEngine {
    private $db;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    public function renderTemplate($template_html, $data) {
        $rendered = $template_html;
        
        $safe_html_fields = ['proposta_descricao', 'proposta_observacoes'];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            
            $placeholder = '{{' . $key . '}}';
            
            if (in_array($key, $safe_html_fields)) {
                $rendered = str_replace($placeholder, nl2br($value), $rendered);
            } else {
                $rendered = str_replace($placeholder, htmlspecialchars($value), $rendered);
            }
        }
        
        $rendered = $this->processLoops($rendered, $data);
        $rendered = $this->processConditionals($rendered, $data);
        $rendered = $this->processFormatters($rendered, $data);
        
        return $rendered;
    }
    
    private function processLoops($html, $data) {
        return $html;
    }
    
    private function processConditionals($html, $data) {
        return $html;
    }
    
    private function processFormatters($html, $data) {
        $html = preg_replace_callback('/\{\{(\w+)\|currency\}\}/', function($matches) use ($data) {
            $key = $matches[1];
            if (isset($data[$key])) {
                return 'R$ ' . number_format((float)$data[$key], 2, ',', '.');
            }
            return $matches[0];
        }, $html);
        
        $html = preg_replace_callback('/\{\{(\w+)\|date\}\}/', function($matches) use ($data) {
            $key = $matches[1];
            if (isset($data[$key])) {
                return date('d/m/Y', strtotime($data[$key]));
            }
            return $matches[0];
        }, $html);
        
        return $html;
    }
    
    public function getPropostaData($proposta_id) {
        $sql = "SELECT p.*, c.nome_fantasia as cliente_nome, c.cnpj as cliente_cnpj, 
                c.endereco as cliente_endereco, c.email as cliente_email,
                u.nome as vendedor_nome, u.email as vendedor_email, u.telefone as vendedor_telefone
                FROM propostas p
                LEFT JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN usuarios u ON p.id_usuario = u.id
                WHERE p.id = ?";
        
        $proposta = run_query($sql, [$proposta_id], true);
        
        if (!$proposta) {
            return null;
        }
        
        $sql_config = "SELECT * FROM configuracoes WHERE ativo=1 ORDER BY id DESC LIMIT 1";
        $config = run_query($sql_config, [], true);
        
        $validade_dias = $proposta['validade_dias'] ?? 30;
        $validade_texto = is_numeric($validade_dias) ? "{$validade_dias} dias" : "30 dias";
        
        $data = [
            'cliente_nome' => $proposta['cliente_nome'] ?? '',
            'cliente_cnpj' => $proposta['cliente_cnpj'] ?? '',
            'cliente_endereco' => $proposta['cliente_endereco'] ?? '',
            'cliente_email' => $proposta['cliente_email'] ?? '',
            
            'vendedor_nome' => $proposta['vendedor_nome'] ?? '',
            'vendedor_email' => $proposta['vendedor_email'] ?? '',
            'vendedor_telefone' => $proposta['vendedor_telefone'] ?? '',
            
            'proposta_numero' => $proposta['codigo_proposta'] ?? ('#' . $proposta['id']),
            'proposta_data' => date('d/m/Y', strtotime($proposta['criado_em'] ?? 'now')),
            'proposta_validade' => $validade_texto,
            'proposta_descricao' => $proposta['descricao'] ?? '',
            
            'valor_total' => number_format((float)($proposta['total_servicos'] ?? 0) + (float)($proposta['total_materiais'] ?? 0), 2, ',', '.'),
            'valor_desconto' => '0,00',
            'valor_final' => number_format((float)($proposta['total_geral'] ?? 0), 2, ',', '.'),
            
            'empresa_nome' => $config['empresa_nome'] ?? 'Inovare Soluções em Saúde',
            'empresa_cnpj' => $config['cnpj'] ?? '',
            'empresa_endereco' => $config['endereco'] ?? '',
        ];
        
        return $data;
    }
    
    public function generateFromModel($modelo_id, $proposta_id) {
        $sql = "SELECT * FROM modelos_documentos WHERE id = ? AND ativo = TRUE";
        $modelo = run_query($sql, [$modelo_id], true);
        
        if (!$modelo) {
            throw new Exception('Modelo não encontrado ou inativo');
        }
        
        $data = $this->getPropostaData($proposta_id);
        
        if (!$data) {
            throw new Exception('Proposta não encontrada');
        }
        
        return $this->renderTemplate($modelo['conteudo_html'], $data);
    }
}
