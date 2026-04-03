import React from "react";
import { motion } from "motion/react";
import { ArrowRight } from "lucide-react";

const flowSteps = [
  { name: "public/index.php", desc: "Front controller" },
  { name: "App\\Core\\Application", desc: "Orquestrador" },
  { name: "Middlewares Globais", desc: "Segurança e Rate Limit" },
  { name: "App\\Core\\FastRouteRouter", desc: "Roteamento" },
  { name: "Middleware de Rota", desc: "Auth, etc." },
  { name: "Controller", desc: "Lógica de negócio" },
  { name: "Model / Resource", desc: "Dados e formatação" },
  { name: "Resposta JSON", desc: "Saída PSR-7" },
];

export function Architecture() {
  return (
    <section
      id="arquitetura"
      className="py-24 bg-slate-900 text-white overflow-hidden"
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-2 gap-16 items-center">
          <div>
            <h2 className="text-3xl md:text-4xl font-bold mb-6">
              Arquitetura e Fluxo
            </h2>
            <p className="text-lg text-slate-300 mb-8 leading-relaxed">
              O front controller real é{" "}
              <code className="text-primary-300 bg-primary-900/30 px-1.5 py-0.5 rounded">
                public/index.php
              </code>
              . Ele monta o container PHP-DI, inicializa o banco, carrega rotas
              e middlewares, e processa a requisição através de uma pipeline
              PSR-15 no padrão onion.
            </p>

            <div className="space-y-6">
              <div>
                <h3 className="text-xl font-semibold text-white mb-2 flex items-center gap-2">
                  <span className="w-8 h-8 rounded-lg bg-primary-500/20 text-primary-400 flex items-center justify-center text-sm">
                    1
                  </span>
                  Core HTTP
                </h3>
                <p className="text-slate-400 pl-10">
                  A <code className="text-slate-300">App\Core\Application</code>{" "}
                  é a fachada principal. Ela delega o despacho ao router e
                  devolve a resposta PSR-7 final.
                </p>
              </div>
              <div>
                <h3 className="text-xl font-semibold text-white mb-2 flex items-center gap-2">
                  <span className="w-8 h-8 rounded-lg bg-primary-500/20 text-primary-400 flex items-center justify-center text-sm">
                    2
                  </span>
                  Container e Configuração
                </h3>
                <p className="text-slate-400 pl-10">
                  O arquivo{" "}
                  <code className="text-slate-300">config/container.php</code>{" "}
                  centraliza definições, factories e valores de ambiente (Banco,
                  JWT, CORS, Redis).
                </p>
              </div>
              <div>
                <h3 className="text-xl font-semibold text-white mb-2 flex items-center gap-2">
                  <span className="w-8 h-8 rounded-lg bg-primary-500/20 text-primary-400 flex items-center justify-center text-sm">
                    3
                  </span>
                  Middlewares Globais
                </h3>
                <p className="text-slate-400 pl-10">
                  Ordem estrita: ErrorHandler, SecurityHeaders, RateLimit, Cors,
                  CsrfGuard. Bloqueiam requisições inadequadas antes do
                  roteamento.
                </p>
              </div>
            </div>
          </div>

          <div className="relative">
            <div className="absolute inset-0 bg-gradient-to-b from-primary-500/10 to-transparent rounded-3xl blur-2xl"></div>
            <div className="relative bg-slate-800/50 border border-slate-700 rounded-3xl p-8 backdrop-blur-sm">
              <h3 className="text-lg font-medium text-slate-300 mb-6 text-center uppercase tracking-wider">
                Fluxo da Requisição
              </h3>
              <div className="space-y-3">
                {flowSteps.map((step, index) => (
                  <motion.div
                    key={index}
                    initial={{ opacity: 0, x: 20 }}
                    whileInView={{ opacity: 1, x: 0 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.3, delay: index * 0.1 }}
                    className="flex items-center gap-4"
                  >
                    <div className="flex-1 bg-slate-800 border border-slate-700 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between group hover:border-primary-500/50 transition-colors">
                      <span className="font-mono text-sm text-primary-300">
                        {step.name}
                      </span>
                      <span className="text-xs text-slate-400 mt-1 sm:mt-0">
                        {step.desc}
                      </span>
                    </div>
                    {index < flowSteps.length - 1 && (
                      <div className="hidden sm:flex text-slate-600">
                        <ArrowRight size={20} />
                      </div>
                    )}
                  </motion.div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
