/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from "react";
import { Navbar } from "./components/Navbar";
import { Hero } from "./components/Hero";
import { Features } from "./components/Features";
import { Architecture } from "./components/Architecture";
import { Security } from "./components/Security";
import { ApiDocs } from "./components/ApiDocs";
import { Installation } from "./components/Installation";
import { Roadmap } from "./components/Roadmap";
import { Footer } from "./components/Footer";

export default function App() {
  return (
    <div className="min-h-screen bg-slate-50 font-sans">
      <Navbar />
      <main>
        <Hero />
        <Features />
        <Architecture />
        <Security />
        <ApiDocs />
        <Installation />
        <Roadmap />
      </main>
      <Footer />
    </div>
  );
}
