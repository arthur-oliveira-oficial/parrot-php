import React from "react";
import { motion } from "motion/react";
import { CheckCircle2, CircleDashed } from "lucide-react";

export function Roadmap() {
  return (
    <section className="py-24 bg-white border-t border-slate-100">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-2 gap-16">
          <div>
            <h2 className="text-3xl md:text-4xl font-bold text-slate-900 mb-6">
              Escopo Atual
            </h2>
            <p className="text-lg text-slate-600 mb-8">
              O framework já entrega um núcleo funcional e seguro para API JSON,
              mas o escopo ainda é propositalmente enxuto para manter a
              previsibilidade e facilidade de evolução.
            </p>

            <ul className="space-y-4">
              {[
                "Fluxo HTTP simples de seguir",
                "Uso de PSR em vez de contratos proprietários",
                "JWT próprio com validação explícita",
                "Autenticação por cookie HttpOnly",
                "Blacklist persistente para logout",
                "Rate limit sensível ao usuário autenticado",
                "Proteção CSRF alinhada ao uso de cookie",
                "Produção com cache distribuído obrigatório",
                "Testes integrados com banco real",
              ].map((item, index) => (
                <motion.li
                  key={index}
                  initial={{ opacity: 0, x: -10 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.05 }}
                  className="flex items-start gap-3 text-slate-700"
                >
                  <CheckCircle2
                    className="text-emerald-500 shrink-0 mt-0.5"
                    size={20}
                  />
                  <span>{item}</span>
                </motion.li>
              ))}
            </ul>
          </div>

          <div className="bg-slate-50 rounded-3xl p-8 lg:p-10 border border-slate-100">
            <h3 className="text-2xl font-bold text-slate-900 mb-6">
              O que não está no escopo (ainda)
            </h3>
            <p className="text-slate-600 mb-8">
              Isso não é uma limitação acidental. É uma escolha de escopo para
              manter o núcleo legível.
            </p>

            <ul className="space-y-4">
              {[
                "Sistema de módulos ou plugins",
                "Refresh token",
                "Autenticação principal via Authorization: Bearer",
                "Filas e Jobs em background",
                "Eventos de domínio",
                "Scheduler / Cron jobs",
                "Documentação OpenAPI gerada automaticamente",
                "Camada administrativa pronta",
                "CLI própria além dos scripts de banco",
              ].map((item, index) => (
                <motion.li
                  key={index}
                  initial={{ opacity: 0, x: 10 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.05 }}
                  className="flex items-start gap-3 text-slate-500"
                >
                  <CircleDashed
                    className="text-slate-400 shrink-0 mt-0.5"
                    size={20}
                  />
                  <span>{item}</span>
                </motion.li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}
