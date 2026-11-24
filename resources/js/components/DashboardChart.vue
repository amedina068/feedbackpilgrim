<template>
  <div class="overflow-hidden">
    <div class="px-4 py-4 sm:px-6">
      <div class="flex justify-between items-baseline">
        <div class="flex space-x-2 items-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
          </svg>
          <h3 class="text-lg font-semibold leading-7 text-fp-hi">
            Contact Activity (Last 7 Days)
          </h3>
        </div>
      </div>
    </div>
    <div class="border-t border-white/5 p-6">
      <div class="h-64">
        <canvas ref="chartCanvas"></canvas>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { Chart, CategoryScale, LinearScale, BarElement, BarController, Title, Tooltip, Legend } from 'chart.js'

Chart.register(CategoryScale, LinearScale, BarElement, BarController, Title, Tooltip, Legend)

const props = defineProps({
  data: {
    type: Array,
    required: true
  }
})

const chartCanvas = ref(null)
const chart = ref(null)

const createChart = () => {
  const ctx = chartCanvas.value.getContext('2d')
  
  const labels = props.data.map(item => item.short_date)
  const dataValues = props.data.map(item => item.logins)
  
  chart.value = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Active Contacts',
        data: dataValues,
        backgroundColor: 'rgba(79, 140, 255, 0.8)',
        borderColor: 'rgba(79, 140, 255, 1)',
        borderWidth: 1,
        borderRadius: 4,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1,
            color: 'rgba(169, 176, 188, 1)'
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.06)'
          }
        },
        x: {
          ticks: {
            color: 'rgba(169, 176, 188, 1)'
          },
          grid: {
            display: false
          }
        }
      },
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: 'white',
          bodyColor: 'white',
          cornerRadius: 6,
          displayColors: false,
          callbacks: {
            title: (context) => {
              const dataIndex = context[0].dataIndex
              return props.data[dataIndex].day_name
            },
            label: (context) => {
              const value = context.parsed.y
              return `${value} active contact${value !== 1 ? 's' : ''}`
            }
          }
        }
      },
      elements: {
        bar: {
          backgroundColor: function(context) {
            const value = context.parsed.y
            if (value === 0) return 'rgba(107, 114, 128, 0.3)'
            return 'rgba(79, 140, 255, 0.8)'
          }
        }
      }
    }
  })
}

const updateChart = () => {
  const labels = props.data.map(item => item.short_date)
  const dataValues = props.data.map(item => item.logins)
  
  chart.value.data.labels = labels
  chart.value.data.datasets[0].data = dataValues
  chart.value.update()
}

onMounted(() => {
  createChart()
})

onBeforeUnmount(() => {
  if (chart.value) {
    chart.value.destroy()
  }
})

watch(() => props.data, () => {
  if (chart.value) {
    updateChart()
  } else {
    createChart()
  }
}, { deep: true })
</script>