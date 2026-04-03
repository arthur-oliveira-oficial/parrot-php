import React from "react";
import { motion } from "motion/react";
import { ArrowRight, Code2, Zap, ShieldCheck } from "lucide-react";
import { CodeBlock } from "./ui/CodeBlock";

export function Hero() {
  const codeSnippet = `<?php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../config/container.php';
$app = $container->get(App\\Core\\Application::class);

// Fluxo HTTP explícito, sem magia
$response = $app->run(App\\Core\\Request::fromGlobals());
$app->emit($response);`;

  return (
    <section className="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
      {/* Background decoration */}
      <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary-100/40 via-slate-50 to-slate-50"></div>
      <div className="absolute top-0 right-0 -translate-y-12 translate-x-1/3 w-[800px] h-[800px] bg-primary-200/20 rounded-full blur-3xl -z-10"></div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="max-w-2xl"
          >
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 border border-primary-100 text-primary-700 text-sm font-medium mb-6">
              <span className="flex h-2 w-2 rounded-full bg-primary-500 animate-pulse"></span>
              PHP 8.4 Ready
            </div>

            <h1 className="text-5xl lg:text-6xl font-bold text-slate-900 tracking-tight leading-[1.1] mb-6">
              Micro-framework REST <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-teal-500">
                sem magia excessiva.
              </span>
            </h1>

            <p className="text-lg text-slate-600 mb-8 leading-relaxed">
              O Parrot PHP entrega um núcleo enxuto, previsível e seguro para
              APIs JSON. Fluxo HTTP explícito, roteamento rápido, injeção de
              dependências e testes integrados contra MySQL.
            </p>

            <div className="flex flex-wrap items-center gap-4">
              <a
                href="#instalacao"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 text-base font-medium text-white bg-slate-900 rounded-full hover:bg-slate-800 transition-all shadow-lg shadow-slate-900/20 hover:shadow-xl hover:shadow-slate-900/30 hover:-translate-y-0.5"
              >
                Começar agora
                <ArrowRight size={18} />
              </a>
              <a
                href="#arquitetura"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 text-base font-medium text-slate-700 bg-white border border-slate-200 rounded-full hover:bg-slate-50 transition-colors"
              >
                Ver documentação
              </a>
            </div>

            <div className="mt-10 flex items-center gap-8 text-sm font-medium text-slate-500">
              <div className="flex items-center gap-2">
                <Zap size={18} className="text-amber-500" />
                <span>FastRoute</span>
              </div>
              <div className="flex items-center gap-2">
                <Code2 size={18} className="text-blue-500" />
                <span>PSR-7 / PSR-15</span>
              </div>
              <div className="flex items-center gap-2">
                <ShieldCheck size={18} className="text-emerald-500" />
                <span>Seguro por padrão</span>
              </div>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: 20, rotate: 2 }}
            animate={{ opacity: 1, x: 0, rotate: 0 }}
            transition={{ duration: 0.6, delay: 0.2, type: "spring" }}
            className="relative lg:ml-auto w-full max-w-lg group"
          >
            <div className="absolute -inset-1 bg-gradient-to-r from-primary-400 to-teal-400 rounded-2xl blur opacity-20 group-hover:opacity-40 transition-opacity duration-500"></div>
            <div className="relative transform transition-transform duration-500 group-hover:-translate-y-1">
              <CodeBlock code={codeSnippet} language="php" />
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
