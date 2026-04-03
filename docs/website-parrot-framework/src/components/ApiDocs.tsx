import React, { useState } from "react";
import { motion, AnimatePresence } from "motion/react";
import { CodeBlock } from "./ui/CodeBlock";

const endpoints = [
  {
    method: "POST",
    path: "/api/auth/login",
    desc: "Autentica usuário e retorna cookie JWT.",
    response: `{
  "data": {
    "id": 1,
    "nome": "Administrador",
    "email": "admin@parrot.com",
    "tipo": "admin"
  },
  "message": "Login realizado com sucesso"
}`,
  },
  {
    method: "GET",
    path: "/api/usuarios",
    desc: "Lista usuários com paginação (Apenas Admin).",
    response: `{
  "data": [...],
  "meta": {
    "pagina_atual": 1,
    "por_pagina": 20,
    "total_registros": 42,
    "total_paginas": 3
  }
}`,
  },
  {
    method: "POST",
    path: "/api/usuarios",
    desc: "Cria novo usuário (Apenas Admin).",
    response: `{
  "data": {
    "id": 2,
    "nome": "Novo Usuário",
    "email": "user@parrot.com",
    "tipo": "user"
  },
  "message": "Usuário criado com sucesso"
}`,
  },
];

export function ApiDocs() {
  const [activeTab, setActiveTab] = useState(0);

  return (
    <section id="api" className="py-24 bg-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-12 gap-12">
          <div className="lg:col-span-5">
            <h2 className="text-3xl md:text-4xl font-bold text-slate-900 mb-6">
              API Pronta para Uso
            </h2>
            <p className="text-lg text-slate-600 mb-8">
              O framework já vem com um módulo de autenticação e gestão de
              usuários completo, seguindo padrões REST e retornando JSON
              estruturado.
            </p>

            <div className="space-y-3">
              {endpoints.map((endpoint, index) => (
                <button
                  key={index}
                  onClick={() => setActiveTab(index)}
                  className={`w-full text-left px-5 py-4 rounded-2xl transition-all flex items-center gap-4 border ${
                    activeTab === index
                      ? "bg-slate-900 text-white border-slate-900 shadow-xl shadow-slate-900/10 scale-[1.02]"
                      : "bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:bg-slate-50"
                  }`}
                >
                  <span
                    className={`text-xs font-bold px-2.5 py-1 rounded-md ${
                      endpoint.method === "GET"
                        ? "bg-blue-500/20 text-blue-500"
                        : endpoint.method === "POST"
                          ? "bg-emerald-500/20 text-emerald-500"
                          : "bg-amber-500/20 text-amber-500"
                    } ${activeTab === index && "bg-opacity-30"}`}
                  >
                    {endpoint.method}
                  </span>
                  <span className="font-mono text-sm font-medium">
                    {endpoint.path}
                  </span>
                </button>
              ))}
            </div>
          </div>

          <div className="lg:col-span-7 relative">
            <div className="absolute -inset-4 bg-slate-50 rounded-3xl -z-10"></div>
            <AnimatePresence mode="wait">
              <motion.div
                key={activeTab}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -10 }}
                transition={{ duration: 0.2 }}
                className="h-full flex flex-col pt-4"
              >
                <div className="mb-6 px-2">
                  <h3 className="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-primary-500"></span>
                    {endpoints[activeTab].desc}
                  </h3>
                </div>
                <div className="flex-1 relative group">
                  <div className="absolute -inset-1 bg-gradient-to-r from-primary-400/20 to-teal-400/20 rounded-2xl blur opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                  <div className="relative h-full">
                    <CodeBlock
                      code={endpoints[activeTab].response}
                      language="json"
                    />
                  </div>
                </div>
              </motion.div>
            </AnimatePresence>
          </div>
        </div>
      </div>
    </section>
  );
}
