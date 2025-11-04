-- Seed de modelos de documentos prontos

-- Modelo 1: Proposta Comercial Padrão
INSERT INTO modelos_documentos (titulo, descricao, categoria, conteudo_html, variaveis_usadas, ativo) VALUES (
'Proposta Comercial Padrão',
'Modelo padrão de proposta comercial com informações básicas e tabela de valores',
'Proposta Comercial',
'<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #2c3e50; margin-bottom: 10px;">{{empresa_nome}}</h1>
        <p style="color: #7f8c8d;">{{empresa_endereco}}</p>
        <p style="color: #7f8c8d;">CNPJ: {{empresa_cnpj}}</p>
    </div>
    
    <hr style="border: 1px solid #ecf0f1; margin: 30px 0;">
    
    <h2 style="color: #3498db; margin-bottom: 20px;">PROPOSTA COMERCIAL Nº {{proposta_numero}}</h2>
    
    <div style="margin-bottom: 20px;">
        <p><strong>Data:</strong> {{proposta_data}}</p>
        <p><strong>Validade:</strong> {{proposta_validade}}</p>
    </div>
    
    <div style="background-color: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="color: #2c3e50; margin-top: 0;">Cliente</h3>
        <p><strong>Razão Social:</strong> {{cliente_nome}}</p>
        <p><strong>CNPJ:</strong> {{cliente_cnpj}}</p>
        <p><strong>Endereço:</strong> {{cliente_endereco}}</p>
        <p><strong>E-mail:</strong> {{cliente_email}}</p>
    </div>
    
    <div style="margin: 30px 0;">
        <h3 style="color: #2c3e50;">Descrição dos Serviços</h3>
        <p style="text-align: justify;">{{proposta_descricao}}</p>
    </div>
    
    <div style="margin: 30px 0;">
        <h3 style="color: #2c3e50;">Valores</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr style="background-color: #3498db; color: white;">
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Descrição</th>
                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Valor</th>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">Valor Total dos Serviços</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">R$ {{valor_total}}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">Desconto</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">R$ {{valor_desconto}}</td>
            </tr>
            <tr style="background-color: #ecf0f1; font-weight: bold;">
                <td style="padding: 10px; border: 1px solid #ddd;">VALOR FINAL</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">R$ {{valor_final}}</td>
            </tr>
        </table>
    </div>
    
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ecf0f1;">
        <p><strong>Responsável pela Proposta:</strong> {{vendedor_nome}}</p>
        <p><strong>Telefone:</strong> {{vendedor_telefone}}</p>
        <p><strong>E-mail:</strong> {{vendedor_email}}</p>
    </div>
    
    <div style="margin-top: 40px; text-align: center; color: #7f8c8d; font-size: 12px;">
        <p>Esta proposta é válida por {{proposta_validade}} a partir da data de emissão.</p>
        <p>Agradecemos pela oportunidade de apresentar esta proposta.</p>
    </div>
</div>',
'cliente_nome,cliente_cnpj,cliente_endereco,cliente_email,vendedor_nome,vendedor_telefone,vendedor_email,proposta_numero,proposta_data,proposta_validade,proposta_descricao,valor_total,valor_desconto,valor_final,empresa_nome,empresa_cnpj,empresa_endereco',
TRUE
);

-- Modelo 2: Orçamento Detalhado
INSERT INTO modelos_documentos (titulo, descricao, categoria, conteudo_html, variaveis_usadas, ativo) VALUES (
'Orçamento Detalhado',
'Modelo de orçamento com layout profissional e seções detalhadas',
'Orçamento',
'<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px;">
        <h1 style="margin: 0; font-size: 32px;">ORÇAMENTO</h1>
        <p style="margin: 10px 0 0 0; font-size: 18px;">Nº {{proposta_numero}}</p>
    </div>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
        <div style="flex: 1;">
            <h3 style="color: #667eea; margin-bottom: 10px;">Empresa</h3>
            <p style="margin: 5px 0;"><strong>{{empresa_nome}}</strong></p>
            <p style="margin: 5px 0; color: #666;">{{empresa_endereco}}</p>
            <p style="margin: 5px 0; color: #666;">CNPJ: {{empresa_cnpj}}</p>
        </div>
        <div style="flex: 1; text-align: right;">
            <h3 style="color: #667eea; margin-bottom: 10px;">Informações</h3>
            <p style="margin: 5px 0;"><strong>Data:</strong> {{proposta_data}}</p>
            <p style="margin: 5px 0;"><strong>Validade:</strong> {{proposta_validade}}</p>
        </div>
    </div>
    
    <div style="background-color: #f8f9fa; padding: 20px; border-left: 4px solid #667eea; margin-bottom: 30px;">
        <h3 style="color: #333; margin-top: 0;">Dados do Cliente</h3>
        <p style="margin: 8px 0;"><strong>Nome/Razão Social:</strong> {{cliente_nome}}</p>
        <p style="margin: 8px 0;"><strong>CNPJ/CPF:</strong> {{cliente_cnpj}}</p>
        <p style="margin: 8px 0;"><strong>Endereço:</strong> {{cliente_endereco}}</p>
        <p style="margin: 8px 0;"><strong>E-mail:</strong> {{cliente_email}}</p>
    </div>
    
    <div style="margin-bottom: 30px;">
        <h3 style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Descrição do Projeto</h3>
        <div style="margin-top: 15px; text-align: justify; line-height: 1.6;">
            {{proposta_descricao}}
        </div>
    </div>
    
    <div style="margin-bottom: 30px;">
        <h3 style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Resumo Financeiro</h3>
        <table style="width: 100%; margin-top: 20px; border-collapse: separate; border-spacing: 0; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <th style="padding: 15px; text-align: left;">Item</th>
                    <th style="padding: 15px; text-align: right;">Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background-color: white;">
                    <td style="padding: 12px; border-bottom: 1px solid #e0e0e0;">Valor Total</td>
                    <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e0e0e0;">{{valor_total}}</td>
                </tr>
                <tr style="background-color: #f8f9fa;">
                    <td style="padding: 12px; border-bottom: 1px solid #e0e0e0;">Desconto</td>
                    <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e0e0e0; color: #27ae60;">{{valor_desconto}}</td>
                </tr>
                <tr style="background-color: #667eea; color: white; font-weight: bold; font-size: 18px;">
                    <td style="padding: 15px;">TOTAL A PAGAR</td>
                    <td style="padding: 15px; text-align: right;">{{valor_final}}</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 30px 0;">
        <p style="margin: 0; color: #856404;"><strong>⚠️ Importante:</strong> Este orçamento tem validade de {{proposta_validade}}. Após esse período, os valores podem sofrer alterações.</p>
    </div>
    
    <div style="margin-top: 40px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
        <h4 style="margin-top: 0; color: #667eea;">Contato do Responsável</h4>
        <p style="margin: 5px 0;">{{vendedor_nome}}</p>
        <p style="margin: 5px 0;">📞 {{vendedor_telefone}}</p>
        <p style="margin: 5px 0;">📧 {{vendedor_email}}</p>
    </div>
</div>',
'cliente_nome,cliente_cnpj,cliente_endereco,cliente_email,vendedor_nome,vendedor_telefone,vendedor_email,proposta_numero,proposta_data,proposta_validade,proposta_descricao,valor_total,valor_desconto,valor_final,empresa_nome,empresa_cnpj,empresa_endereco',
TRUE
);

-- Modelo 3: Contrato de Serviços
INSERT INTO modelos_documentos (titulo, descricao, categoria, conteudo_html, variaveis_usadas, ativo) VALUES (
'Contrato de Prestação de Serviços',
'Modelo básico de contrato com cláusulas padrão',
'Contrato',
'<div style="font-family: Times New Roman, serif; max-width: 800px; margin: 0 auto; padding: 40px; line-height: 1.8;">
    <h1 style="text-align: center; font-size: 18px; margin-bottom: 30px;">CONTRATO DE PRESTAÇÃO DE SERVIÇOS</h1>
    
    <p style="text-align: justify; margin-bottom: 20px;">
        Pelo presente instrumento particular, de um lado <strong>{{empresa_nome}}</strong>, 
        pessoa jurídica de direito privado, inscrita no CNPJ sob o nº <strong>{{empresa_cnpj}}</strong>, 
        com sede em <strong>{{empresa_endereco}}</strong>, doravante denominada <strong>CONTRATADA</strong>, 
        e de outro lado <strong>{{cliente_nome}}</strong>, inscrita no CNPJ sob o nº <strong>{{cliente_cnpj}}</strong>, 
        com sede em <strong>{{cliente_endereco}}</strong>, doravante denominada <strong>CONTRATANTE</strong>, 
        têm entre si justo e contratado o que segue:
    </p>
    
    <h3 style="margin-top: 30px;">CLÁUSULA PRIMEIRA - DO OBJETO</h3>
    <p style="text-align: justify;">
        O presente contrato tem por objeto a prestação de serviços conforme descrito:
    </p>
    <div style="margin-left: 30px; margin-top: 15px; margin-bottom: 15px;">
        {{proposta_descricao}}
    </div>
    
    <h3 style="margin-top: 30px;">CLÁUSULA SEGUNDA - DO VALOR E FORMA DE PAGAMENTO</h3>
    <p style="text-align: justify;">
        Pelos serviços objeto deste contrato, a CONTRATANTE pagará à CONTRATADA o valor de 
        <strong>R$ {{valor_final}}</strong>, conforme discriminação abaixo:
    </p>
    <table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px; border: 1px solid #000;">Valor dos Serviços:</td>
            <td style="padding: 8px; border: 1px solid #000; text-align: right;">R$ {{valor_total}}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #000;">Desconto:</td>
            <td style="padding: 8px; border: 1px solid #000; text-align: right;">R$ {{valor_desconto}}</td>
        </tr>
        <tr style="font-weight: bold;">
            <td style="padding: 8px; border: 1px solid #000;">VALOR TOTAL:</td>
            <td style="padding: 8px; border: 1px solid #000; text-align: right;">R$ {{valor_final}}</td>
        </tr>
    </table>
    
    <h3 style="margin-top: 30px;">CLÁUSULA TERCEIRA - DA VIGÊNCIA</h3>
    <p style="text-align: justify;">
        O presente contrato entra em vigor na data de sua assinatura e terá validade 
        conforme especificado na proposta comercial nº {{proposta_numero}}.
    </p>
    
    <h3 style="margin-top: 30px;">CLÁUSULA QUARTA - DAS OBRIGAÇÕES DA CONTRATADA</h3>
    <p style="text-align: justify;">
        A CONTRATADA obriga-se a:
    </p>
    <ul style="text-align: justify;">
        <li>Executar os serviços de acordo com as especificações técnicas estabelecidas;</li>
        <li>Manter sigilo sobre todas as informações da CONTRATANTE;</li>
        <li>Prestar suporte técnico durante a vigência do contrato;</li>
        <li>Entregar os produtos/serviços dentro dos prazos acordados.</li>
    </ul>
    
    <h3 style="margin-top: 30px;">CLÁUSULA QUINTA - DAS OBRIGAÇÕES DA CONTRATANTE</h3>
    <p style="text-align: justify;">
        A CONTRATANTE obriga-se a:
    </p>
    <ul style="text-align: justify;">
        <li>Efetuar o pagamento nas datas estipuladas;</li>
        <li>Fornecer todas as informações necessárias para a execução dos serviços;</li>
        <li>Disponibilizar os recursos necessários quando aplicável.</li>
    </ul>
    
    <h3 style="margin-top: 30px;">CLÁUSULA SEXTA - DO FORO</h3>
    <p style="text-align: justify;">
        Fica eleito o foro da comarca de localização da CONTRATADA para dirimir quaisquer 
        dúvidas oriundas do presente contrato.
    </p>
    
    <p style="text-align: justify; margin-top: 40px; margin-bottom: 60px;">
        E, por estarem assim justas e contratadas, as partes assinam o presente instrumento 
        em duas vias de igual teor e forma, na presença de duas testemunhas.
    </p>
    
    <div style="margin-top: 80px;">
        <table style="width: 100%;">
            <tr>
                <td style="text-align: center; padding: 20px;">
                    <div style="border-top: 1px solid #000; width: 250px; margin: 0 auto; padding-top: 10px;">
                        <strong>{{empresa_nome}}</strong><br>
                        CONTRATADA
                    </div>
                </td>
                <td style="text-align: center; padding: 20px;">
                    <div style="border-top: 1px solid #000; width: 250px; margin: 0 auto; padding-top: 10px;">
                        <strong>{{cliente_nome}}</strong><br>
                        CONTRATANTE
                    </div>
                </td>
            </tr>
        </table>
    </div>
    
    <div style="margin-top: 60px; text-align: center; color: #666; font-size: 12px;">
        <p>Data: {{proposta_data}}</p>
        <p>Responsável: {{vendedor_nome}} | {{vendedor_email}} | {{vendedor_telefone}}</p>
    </div>
</div>',
'cliente_nome,cliente_cnpj,cliente_endereco,cliente_email,vendedor_nome,vendedor_telefone,vendedor_email,proposta_numero,proposta_data,proposta_validade,proposta_descricao,valor_total,valor_desconto,valor_final,empresa_nome,empresa_cnpj,empresa_endereco',
TRUE
);
