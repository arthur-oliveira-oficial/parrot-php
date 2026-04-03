import React from "react";
import { motion } from "motion/react";
import { ShieldAlert, KeyRound, Ban, Cookie } from "lucide-react";

export function Security() {
  return (
    <section id="seguranca" className="py-24 bg-slate-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-3xl md:text-4xl font-bold text-slate-900 mb-4">
            Segurança e Autenticação
          </h2>
          <p className="text-lg text-slate-600">
            Proteção robusta por padrão. O Parrot PHP implementa as melhores
            práticas de segurança para APIs modernas.
          </p>
        </div>

        <div className="grid md:grid-cols-2 gap-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="bg-white rounded-2xl p-8 shadow-sm border border-slate-100"
          >
            <div className="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6">
              <KeyRound size={24} />
            </div>
            <h3 className="text-xl font-bold text-slate-900 mb-4">
              JWT via Cookie HttpOnly
            </h3>
            <ul className="space-y-3 text-slate-600">
              <li className="flex items-start gap-2">
                <span className="text-indigo-500 mt-1">•</span>
                <span>
                  Assinatura HS256 com validação estrita de claims (iss, aud,
                  exp, nbf).
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-indigo-500 mt-1">•</span>
                <span>
                  Entregue exclusivamente via cookie{" "}
                  <code className="text-sm bg-slate-100 px-1 rounded">
                    HttpOnly
                  </code>
                  ,{" "}
                  <code className="text-sm bg-slate-100 px-1 rounded">
                    SameSite=Strict
                  </code>
                  .
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-indigo-500 mt-1">•</span>
                <span>
                  Flag{" "}
                  <code className="text-sm bg-slate-100 px-1 rounded">
                    Secure
                  </code>{" "}
                  ativada automaticamente em produção/HTTPS.
                </span>
              </li>
            </ul>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.1 }}
            className="bg-white rounded-2xl p-8 shadow-sm border border-slate-100"
          >
            <div className="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mb-6">
              <Ban size={24} />
            </div>
            <h3 className="text-xl font-bold text-slate-900 mb-4">
              Logout e Blacklist
            </h3>
            <ul className="space-y-3 text-slate-600">
              <li className="flex items-start gap-2">
                <span className="text-rose-500 mt-1">•</span>
                <span>Logout persiste o JTI do token em banco de dados.</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-rose-500 mt-1">•</span>
                <span>
                  Cache distribuído (Redis) para verificação ultrarrápida de
                  tokens revogados.
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-rose-500 mt-1">•</span>
                <span>
                  Limpeza automática de tokens expirados da blacklist.
                </span>
              </li>
            </ul>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.2 }}
            className="bg-white rounded-2xl p-8 shadow-sm border border-slate-100"
          >
            <div className="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-6">
              <ShieldAlert size={24} />
            </div>
            <h3 className="text-xl font-bold text-slate-900 mb-4">
              Rate Limit e CORS
            </h3>
            <ul className="space-y-3 text-slate-600">
              <li className="flex items-start gap-2">
                <span className="text-amber-500 mt-1">•</span>
                <span>
                  Rate limit global (60/min) e específico para login (5/15min).
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-amber-500 mt-1">•</span>
                <span>
                  Identificação por{" "}
                  <code className="text-sm bg-slate-100 px-1 rounded">sub</code>{" "}
                  do JWT ou IP (com suporte a proxy reverso).
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-amber-500 mt-1">•</span>
                <span>
                  CORS estrito com whitelist explícita de origens permitidas.
                </span>
              </li>
            </ul>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.3 }}
            className="bg-white rounded-2xl p-8 shadow-sm border border-slate-100"
          >
            <div className="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6">
              <Cookie size={24} />
            </div>
            <h3 className="text-xl font-bold text-slate-900 mb-4">
              Proteções de Domínio
            </h3>
            <ul className="space-y-3 text-slate-600">
              <li className="flex items-start gap-2">
                <span className="text-emerald-500 mt-1">•</span>
                <span>
                  CSRF Guard bloqueia escritas autenticadas fora da whitelist.
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-emerald-500 mt-1">•</span>
                <span>Prevenção contra IDOR e validação estrita de IDs.</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-emerald-500 mt-1">•</span>
                <span>
                  Senhas com{" "}
                  <code className="text-sm bg-slate-100 px-1 rounded">
                    PASSWORD_ARGON2ID
                  </code>{" "}
                  e exigência de senha atual para alterações.
                </span>
              </li>
            </ul>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
