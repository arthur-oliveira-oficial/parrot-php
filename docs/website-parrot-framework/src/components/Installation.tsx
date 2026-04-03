import React from "react";
import { motion } from "motion/react";
import { Server, TerminalSquare, TestTube } from "lucide-react";
import { CodeBlock } from "./ui/CodeBlock";

export function Installation() {
  return (
    <section id="instalacao" className="py-24 bg-slate-900 text-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-3xl md:text-4xl font-bold mb-4">
            Do Desenvolvimento à Produção
          </h2>
          <p className="text-lg text-slate-400">
            Ambiente configurável via .env, scripts de banco integrados e testes
            automatizados.
          </p>
        </div>

        <div className="grid lg:grid-cols-3 gap-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="bg-slate-800/50 border border-slate-700 rounded-2xl p-8"
          >
            <div className="flex items-center gap-3 mb-6">
              <TerminalSquare className="text-primary-400" size={24} />
              <h3 className="text-xl font-bold">Instalação</h3>
            </div>
            <p className="text-slate-400 mb-6 text-sm">
              Clone o repositório, instale as dependências e rode as migrations.
            </p>
            <div className="space-y-4">
              <CodeBlock
                code={`composer install\ncp .env.example .env`}
                language="bash"
              />
              <CodeBlock
                code={`php database/scripts/migrate.php\nphp database/scripts/seed.php`}
                language="bash"
              />
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.1 }}
            className="bg-slate-800/50 border border-slate-700 rounded-2xl p-8"
          >
            <div className="flex items-center gap-3 mb-6">
              <Server className="text-rose-400" size={24} />
              <h3 className="text-xl font-bold">Produção</h3>
            </div>
            <p className="text-slate-400 mb-6 text-sm">
              Em produção, o framework exige cache distribuído e configurações
              explícitas.
            </p>
            <CodeBlock
              code={`APP_ENV=production\nAPP_DEBUG=false\nCACHE_STORE=redis\nTRUSTED_PROXY_IPS=10.0.0.0/8`}
              language="env"
            />
            <ul className="mt-6 space-y-2 text-sm text-slate-400">
              <li>
                • <code className="text-slate-300">.env</code> não é carregado
                automaticamente
              </li>
              <li>• Redis é exigido para rate limit</li>
              <li>• Cookies usam flag Secure</li>
            </ul>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.2 }}
            className="bg-slate-800/50 border border-slate-700 rounded-2xl p-8"
          >
            <div className="flex items-center gap-3 mb-6">
              <TestTube className="text-cyan-400" size={24} />
              <h3 className="text-xl font-bold">Testes</h3>
            </div>
            <p className="text-slate-400 mb-6 text-sm">
              Suíte de testes integrada validando o comportamento real contra
              MySQL.
            </p>
            <CodeBlock code={`./vendor/bin/phpunit`} language="bash" />
            <ul className="mt-6 space-y-2 text-sm text-slate-400">
              <li>• Banco recriado a cada teste</li>
              <li>• Cobertura de Auth e CRUD</li>
              <li>• Validação de Rate Limit</li>
              <li>• Proteção contra IDOR testada</li>
            </ul>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
