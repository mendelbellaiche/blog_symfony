import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

import hljs from 'highlight.js/lib/core';
import php from 'highlight.js/lib/languages/php';
import bash from 'highlight.js/lib/languages/bash';
import javascript from 'highlight.js/lib/languages/javascript';
import yaml from 'highlight.js/lib/languages/yaml';
import twig from 'highlight.js/lib/languages/twig';
import sql from 'highlight.js/lib/languages/sql';
import 'highlight.js/styles/github-dark.css';

hljs.registerLanguage('php', php);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('yaml', yaml);
hljs.registerLanguage('twig', twig);
hljs.registerLanguage('sql', sql);

// turbo:load est déclenché à chaque changement de page, y compris le premier chargement
document.addEventListener('turbo:load', () => {
    hljs.highlightAll();
});
