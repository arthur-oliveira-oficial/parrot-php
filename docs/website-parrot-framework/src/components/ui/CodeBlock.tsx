import React from "react";

interface CodeBlockProps {
  code: string;
  language?: string;
}

export function CodeBlock({ code, language = "php" }: CodeBlockProps) {
  return (
    <div className="relative rounded-xl bg-slate-900 overflow-hidden border border-slate-800 shadow-2xl">
      <div className="flex items-center px-4 py-3 border-b border-slate-800 bg-slate-900/50">
        <div className="flex space-x-2">
          <div className="w-3 h-3 rounded-full bg-rose-500/80"></div>
          <div className="w-3 h-3 rounded-full bg-amber-500/80"></div>
          <div className="w-3 h-3 rounded-full bg-emerald-500/80"></div>
        </div>
        {language && (
          <div className="ml-4 text-xs font-mono text-slate-500 uppercase tracking-wider">
            {language}
          </div>
        )}
      </div>
      <div className="p-4 overflow-x-auto">
        <pre className="text-sm font-mono text-slate-300 leading-relaxed">
          <code>{code}</code>
        </pre>
      </div>
    </div>
  );
}
