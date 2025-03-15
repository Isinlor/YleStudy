<template>
  <div class="grid place-items-center min-h-screen">
    <div class="max-w-screen-md bg-white p-6 rounded-lg shadow-md">
      <div v-for="{ sentence } in sentences">
        <div class="text-xl text-center">{{ sentence.fi }}</div>
        <div class="text-xl text-center">{{ sentence.en }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">

type Sentence = { fi: string; en: string; }
type Phrase = { fi: { exact: string; base: string; }; en: string; };
type SentenceWithPhrases = { sentence: Sentence; phrases: Phrase[]; };

const sentences: SentenceWithPhrases[] = [
  {
    sentence: {
      fi: 'Eduskuntavaalit ovat kuukauden päästä.',
      en: 'The parliamentary elections are in a month.',
    },
    phrases: [
      { fi: { exact: 'Eduskuntavaalit', base: 'eduskuntavaalit' }, en: 'parliamentary elections' },
      { fi: { exact: 'ovat', base: 'olla' }, en: 'are' },
      { fi: { exact: 'kuukauden', base: 'kuukausi' }, en: 'month' },
      { fi: { exact: 'päästä', base: 'päästä' }, en: 'from' },
    ]
  }
];

</script>
<style>
</style>
