import React from "react";
import { Terminal } from "lucide-react";

export function Footer() {
  return (
    <footer className="bg-slate-950 py-12 border-t border-slate-900">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col md:flex-row justify-between items-center gap-6">
          <div className="flex items-center gap-2">
            <div className="text-primary-500">
              <Terminal size={24} />
            </div>
            <span className="text-xl font-bold tracking-tight text-white">
              Parrot <span className="text-primary-500">PHP</span>
            </span>
          </div>

          <div className="text-slate-400 text-sm">
            © {new Date().getFullYear()} Parrot PHP Framework. Open source sob
            licença MIT.
          </div>

          <div className="flex gap-6">
            <a
              href="#"
              className="text-slate-400 hover:text-white transition-colors"
            >
              GitHub
            </a>
            <a
              href="#"
              className="text-slate-400 hover:text-white transition-colors"
            >
              Documentação
            </a>
            <a
              href="#"
              className="text-slate-400 hover:text-white transition-colors"
            >
              Twitter
            </a>
          </div>
        </div>
      </div>
    </footer>
  );
}
