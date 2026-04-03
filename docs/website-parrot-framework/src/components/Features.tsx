import React from "react";
import { motion } from "motion/react";
import { Layers, Route, Database, Lock, Shield, TestTube2 } from "lucide-react";

const features = [
  {
    title: "Arquitetura Explícita",
    description:
      "Fluxo HTTP simples de seguir. Sem magia escondida. Você sabe exatamente por onde a requisição passa.",
    icon: Layers,
    color: "text-blue-500",
    bg: "bg-blue-50",
  },
  {
    title: "Roteamento Rápido",
    description:
      "Utiliza nikic/fast-route para performance máxima, com suporte a cache de rotas em produção.",
    icon: Route,
    color: "text-amber-500",
    bg: "bg-amber-50",
  },
  {
    title: "ORM Robusto",
    description:
      "Integração com illuminate/database (Eloquent) para persistência de dados elegante e poderosa.",
    icon: Database,
    color: "text-emerald-500",
    bg: "bg-emerald-50",
  },
  {
    title: "Autenticação Segura",
    description:
      "JWT manual com validação explícita, entregue exclusivamente via cookie HttpOnly e SameSite=Strict.",
    icon: Lock,
    color: "text-indigo-500",
    bg: "bg-indigo-50",
  },
  {
    title: "Proteção Integrada",
    description:
      "Rate limit, CORS, CSRF, headers de segurança e proteção contra IDOR já configurados por padrão.",
    icon: Shield,
    color: "text-rose-500",
    bg: "bg-rose-50",
  },
  {
    title: "Testes com Banco Real",
    description:
      "Suíte de testes integrada validando o comportamento real do framework contra MySQL/MariaDB.",
    icon: TestTube2,
    color: "text-cyan-500",
    bg: "bg-cyan-50",
  },
];

export function Features() {
  return (
    <section id="proposta" className="py-24 bg-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-3xl md:text-4xl font-bold text-slate-900 mb-4">
            Proposta do Framework
          </h2>
          <p className="text-lg text-slate-600">
            Desenhado para entregar uma base REST moderna, facilitando a
            leitura, manutenção e evolução da sua API.
          </p>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          {features.map((feature, index) => (
            <motion.div
              key={index}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4, delay: index * 0.1 }}
              className="p-6 rounded-2xl border border-slate-100 bg-slate-50 hover:bg-white hover:shadow-xl hover:shadow-slate-200/50 transition-all group"
            >
              <div
                className={`w-12 h-12 rounded-xl ${feature.bg} ${feature.color} flex items-center justify-center mb-6 group-hover:scale-110 transition-transform`}
              >
                <feature.icon size={24} />
              </div>
              <h3 className="text-xl font-semibold text-slate-900 mb-3">
                {feature.title}
              </h3>
              <p className="text-slate-600 leading-relaxed">
                {feature.description}
              </p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
