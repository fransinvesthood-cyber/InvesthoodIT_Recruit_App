<?php
if (!isset($user)) { $user = current_user(); }
?>
<div class="pm-chatbot" id="pmChatbot" data-api-url="<?= e(url('programme/chatbot_api.php')) ?>">
<button type="button" class="pm-chatbot__launcher" id="pmChatbotLauncher" aria-label="Open Programme Assistant" aria-controls="pmChatbotPanel" aria-expanded="false"><span class="pm-chatbot__launcher-icon"><i class="fas fa-robot"></i></span><span class="pm-chatbot__launcher-label">Programme Assistant</span></button>
<section class="pm-chatbot__panel" id="pmChatbotPanel" role="dialog" aria-modal="false" aria-labelledby="pmChatbotTitle" hidden>
<header class="pm-chatbot__header"><div class="pm-chatbot__identity"><div class="pm-chatbot__avatar"><i class="fas fa-robot"></i></div><div><h2 id="pmChatbotTitle">Programme Assistant</h2><p><span class="pm-chatbot__status-dot"></span> Connected to your programme data</p></div></div><button type="button" class="pm-chatbot__close" id="pmChatbotClose" aria-label="Close"><i class="fas fa-times"></i></button></header>
<div class="pm-chatbot__messages" id="pmChatbotMessages" aria-live="polite"><div class="pm-chatbot__message pm-chatbot__message--assistant"><div class="pm-chatbot__bubble">Hello, <?= e($user['first_name'] ?? 'Programme Manager') ?>. I can help you understand your assigned programmes, cohorts and candidates.</div></div></div>
<div class="pm-chatbot__quick-actions" id="pmChatbotQuickActions"><button type="button" data-prompt="Give me an overview of my programmes.">Programme overview</button><button type="button" data-prompt="How many active cohorts do I have?">Active cohorts</button><button type="button" data-prompt="How many candidates do I have?">Candidates</button><button type="button" data-prompt="Show my programme performance.">Performance</button></div>
<form class="pm-chatbot__composer" id="pmChatbotForm" autocomplete="off"><label class="sr-only" for="pmChatbotInput">Ask the Programme Assistant</label><input id="pmChatbotInput" name="message" type="text" maxlength="500" placeholder="Ask about your programmes..." required><button type="submit" id="pmChatbotSend" aria-label="Send"><i class="fas fa-paper-plane"></i></button></form>
<p class="pm-chatbot__hint">Answers are limited to data you are authorised to view.</p>
</section></div>
