<template>
  <div class="p-8 bg-background">
    <h1 class="text-4xl font-bold text-foreground mb-8">NextBook Color System</h1>

    <!-- CSS Variables Colors -->
    <section class="mb-8">
      <h2 class="text-2xl font-semibold text-foreground mb-4">System Colors</h2>
      <div class="grid grid-cols-3 gap-4">
        <div class="p-4 bg-primary text-primary-foreground rounded">Primary</div>
        <div class="p-4 bg-secondary text-secondary-foreground rounded">Secondary</div>
        <div class="p-4 bg-accent text-accent-foreground rounded">Accent</div>
      </div>
    </section>

    <!-- Custom Colors -->
    <section class="mb-8">
      <h2 class="text-2xl font-semibold text-foreground mb-4">Custom Colors</h2>
      <div class="grid grid-cols-4 gap-4">
        <div class="p-4 bg-nextbook-purple-500 text-white rounded">Purple 500</div>
        <div class="p-4 bg-nextbook-lavender-300 text-foreground rounded">Lavender 300</div>
        <div class="p-4 bg-nextbook-yellow-500 text-foreground rounded">Yellow 500</div>
        <div class="p-4 bg-nextbook-blue-gray-200 text-foreground rounded">Blue-Gray 200</div>
      </div>
    </section>
  </div>
</template>
