<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

interface FamilyMember {
    id?: number;
    name: string;
    gender: 'male' | 'female';
    birth_date: string | null;
    death_date: string | null;
    wife_name: string | null;
    description: string | null;
    parent_id: number | null;
    children: FamilyMember[];
}

const members = ref<FamilyMember[]>([]);
const showForm = ref(false);
const formMode = ref<'grandfather' | 'child' | 'edit'>('grandfather');
const selectedMember = ref<FamilyMember | null>(null);
const isFormOpen = ref(false);
const chartContainer = ref<HTMLDivElement | null>(null);

let echarts: any = null;
let chart: any = null;

const form = ref({
    id: undefined as number | undefined,
    name: '',
    gender: 'male' as 'male' | 'female',
    birth_date: '',
    death_date: '',
    wife_name: '',
    description: '',
    parent_id: null as number | null,
});

const loading = ref(false);
const zoomLevel = ref(1);

const zoomIn = () => {
    zoomLevel.value = Math.min(zoomLevel.value + 0.25, 4);
    applyZoom();
};

const zoomOut = () => {
    zoomLevel.value = Math.max(zoomLevel.value - 0.25, 0.25);
    applyZoom();
};

const applyZoom = () => {
    if (!chart || !chartContainer.value) return;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const baseSize = Math.max(vw, vh);
    const size = Math.round(baseSize * zoomLevel.value);
    chartContainer.value.style.width = size + 'px';
    chartContainer.value.style.height = size + 'px';
    chart.resize({ width: size, height: size });
};

const buildTree = (flat: FamilyMember[]): FamilyMember[] => {
    const map = new Map<number, any>();
    const roots: any[] = [];

    flat.forEach((m) => {
        if (m.id) {
            map.set(m.id, {
                id: m.id,
                name: m.name,
                gender: m.gender,
                birth_date: m.birth_date,
                death_date: m.death_date,
                wife_name: m.wife_name,
                description: m.description,
                parent_id: m.parent_id,
                children: [],
            });
        }
    });

    flat.forEach((m) => {
        if (!m.id) return;
        const node = map.get(m.id);
        if (!node) return;

        if (m.parent_id && map.has(m.parent_id)) {
            const parent = map.get(m.parent_id);
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    });

    const calculateValue = (node: any): number => {
        if (node.children.length === 0) {
            node.value = 1;
            return 1;
        }
        let sum = 0;
        node.children.forEach((child: any) => {
            sum += calculateValue(child);
        });
        node.value = sum;
        return sum;
    };

    roots.forEach((root) => calculateValue(root));

    return roots;
};

const LEVEL1_COLORS = ['#4D96FF'];
const LEVEL2_COLORS = [
    '#FF6B6B',
    '#6BCB77',
    '#FFD93D',
    '#FF6EC7',
    '#845EC2',
    '#FFA07A',
    '#20C997',
    '#E84393',
];

const hexToRgba = (hex: string, opacity: number): string => {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${opacity})`;
};

const assignLighterColors = (
    nodes: any[],
    parentColor: string,
    depth: number,
) => {
    const opacity = Math.max(1 - (depth - 2) * 0.2, 0.25);
    nodes.forEach((node: any) => {
        node.itemStyle = { color: hexToRgba(parentColor, opacity) };
        assignLighterColors(node.children || [], parentColor, depth + 1);
    });
};

const assignColors = (roots: any[]) => {
    if (roots.length !== 1) return;

    roots[0].itemStyle = { color: LEVEL1_COLORS[0] };

    roots[0].children?.forEach((child: any, index: number) => {
        const color = LEVEL2_COLORS[index % LEVEL2_COLORS.length];
        child.itemStyle = { color };
        assignLighterColors(child.children || [], color, 3);
    });
};

const findParentName = (id: number): string | null => {
    const search = (
        list: FamilyMember[],
        parent: FamilyMember | null,
    ): string | null => {
        for (const m of list) {
            if (m.id === id) return parent?.name || null;
            if (m.children?.length) {
                const f = search(m.children, m);
                if (f !== undefined) return f;
            }
        }
        return undefined as any;
    };
    return search(members.value, null);
};

const findMember = (id: number): FamilyMember | null => {
    // console.log(id);
    const search = (list: FamilyMember[]): FamilyMember | null => {
        for (const m of list) {
            if (m.id === id) return m;
            if (m.children?.length) {
                const f = search(m.children);
                if (f) return f;
            }
        }
        return null;
    };
    return search(members.value);
};

const BASE_FONT_SIZE = 18;
const CHAR_WIDTH = BASE_FONT_SIZE * 3;
const MIN_LEVEL_WIDTH = 150;
const CHART_PADDING = 120;

const getMaxNameLengthPerLevel = (
    nodes: any[],
    depth: number = 1,
    result: Map<number, number> = new Map(),
): Map<number, number> => {
    nodes.forEach((node: any) => {
        const current = result.get(depth) || 0;
        result.set(depth, Math.max(current, node.name.length));
        if (node.children?.length) {
            getMaxNameLengthPerLevel(node.children, depth + 1, result);
        }
    });
    return result;
};

const generateLevels = (maxNamePerLevel: Map<number, number>) => {
    const levels: any[] = [{}];
    const totalLevels = maxNamePerLevel.size;

    let totalPixels = 0;
    const widths: number[] = [];
    for (let i = 1; i <= totalLevels; i++) {
        const maxChars = maxNamePerLevel.get(i) || 1;
        const width = Math.max(
            maxChars * CHAR_WIDTH + CHART_PADDING,
            MIN_LEVEL_WIDTH,
        );
        widths.push(width);
        totalPixels += width;
    }

    let offset = 0;
    for (let i = 0; i < widths.length; i++) {
        const r0 = ((offset / totalPixels) * 100).toFixed(1) + '%';
        offset += widths[i];
        const r = Math.min((offset / totalPixels) * 100, 100).toFixed(1) + '%';

        const levelNum = i + 1;
        const fontScale = Math.max(1 - (levelNum - 1) * 0.05, 0.7);
        const fontSize = Math.max(
            Math.round(BASE_FONT_SIZE * zoomLevel.value * fontScale),
            12,
        );

        levels.push({
            r0,
            r,
            label: {
                rotate: 'tangential',
                color: '#fff',
                fontSize,
                show: true,
                overflow: 'truncate',
                ellipsis: '..',
                formatter: '{b}',
            },
            itemStyle: {
                borderWidth: 1,
            },
        });
    }

    return levels;
};

const initChart = async () => {
    echarts = await import('echarts');
    if (!chartContainer.value) return;
    chart = echarts.init(chartContainer.value, undefined, {
        width: undefined,
        height: undefined,
        renderer: 'canvas',
    });
    renderChart();

    chart.off('click');
    chart.on('click', (params: any) => {
        if (params.name && params.name !== 'لا توجد بيانات') {
            const m = findMember(params.data.id);
            // console.log(m);
            if (m?.id) {
                selectedMember.value = m;
            }
        } else {
            selectedMember.value = null;
        }
    });

    setTimeout(() => chart?.resize(), 100);
};

const renderChart = () => {
    if (!chart || !echarts) return;

    const data =
        members.value.length > 0
            ? members.value
            : [{ name: 'لا توجد بيانات', value: 1 }];

    let levels: any[] = [{}];
    if (members.value.length > 0) {
        const maxNamePerLevel = getMaxNameLengthPerLevel(data as any[]);
        levels = generateLevels(maxNamePerLevel);
        assignColors(data as any[]);
    }

    chart.setOption(
        {
            backgroundColor: '#1a1a2e',
            tooltip: {
                trigger: 'item',
                formatter: (params: any) => {
                    const m = findMember(params.data.id);
                    if (!m) return params.name;
                    let html = `<strong>${m.name}</strong><br/>الجنس: ${m.gender === 'male' ? 'ذكر' : 'أنثى'}`;
                    if (m.birth_date) html += `<br/>الميلاد: ${m.birth_date}`;
                    if (m.death_date) html += `<br/>الوفاة: ${m.death_date}`;
                    if (m.wife_name) html += `<br/>الزوجة: ${m.wife_name}`;
                    return html;
                },
            },
            series: [
                {
                    type: 'sunburst',
                    data: data,
                    radius: ['0%', '100%'],
                    center: ['50%', '50%'],
                    sort: undefined,
                    color: [
                        '#FF6B6B',
                        '#FFD93D',
                        '#6BCB77',
                        '#4D96FF',
                        '#FF6EC7',
                        '#845EC2',
                        '#FFA07A',
                        '#20C997',
                        '#FFB6C1',
                        '#00CED1',
                    ],
                    emphasis: { focus: 'ancestor' },
                    levels: levels,
                },
            ],
        },
        true,
    );

    setTimeout(() => chart.resize(), 50);
};

const fetchData = async () => {
    try {
        const res = await fetch('/api/family-members');
        members.value = buildTree(await res.json());
        renderChart();
    } catch (e) {
        console.error(e);
    }
};

onMounted(() => {
    setTimeout(() => {
        initChart();
        fetchData();
    }, 200);
});

onUnmounted(() => {
    chart?.dispose();
    chart = null;
});

const openForm = () => {
    isFormOpen.value = true;
    showForm.value = true;
    setMode('grandfather');
};

const openFormForUpdate = () => {
    isFormOpen.value = true;
    showForm.value = true;
    setMode('edit');
};

const openFormForAddChild = () => {
    isFormOpen.value = true;
    showForm.value = true;
    setMode('child');
};

const closeForm = () => {
    isFormOpen.value = false;
    showForm.value = false;
    resetForm();
};

const setMode = (m: 'grandfather' | 'child' | 'edit') => {
    formMode.value = m;
    if (m === 'grandfather') {
        form.value = {
            id: undefined,
            name: '',
            gender: 'male',
            birth_date: '',
            death_date: '',
            wife_name: '',
            description: '',
            parent_id: null,
        };
    } else if (m === 'child' && selectedMember.value) {
        form.value = {
            id: undefined,
            name: '',
            gender: 'male',
            birth_date: '',
            death_date: '',
            wife_name: '',
            description: '',
            parent_id: selectedMember.value.id || null,
        };
    } else if (m === 'edit' && selectedMember.value) {
        form.value = {
            id: selectedMember.value.id,
            name: selectedMember.value.name,
            gender: selectedMember.value.gender,
            birth_date: selectedMember.value.birth_date || '',
            death_date: selectedMember.value.death_date || '',
            wife_name: selectedMember.value.wife_name || '',
            description: selectedMember.value.description || '',
            parent_id: selectedMember.value.parent_id,
        };
    }
};

const resetForm = () => {
    form.value = {
        id: undefined,
        name: '',
        gender: 'male',
        birth_date: '',
        death_date: '',
        wife_name: '',
        description: '',
        parent_id: null,
    };
    formMode.value = 'grandfather';
};

const submitForm = async () => {
    if (!form.value.name.trim()) {
        alert('الرجاء إدخال الاسم');
        return;
    }
    loading.value = true;
    try {
        const url = form.value.id
            ? `/api/family-members/${form.value.id}`
            : '/api/family-members';
        const method = form.value.id ? 'PUT' : 'POST';
        await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form.value),
        });

        if (formMode.value === 'edit' && selectedMember.value) {
            selectedMember.value.name = form.value.name;
        }

        await fetchData();
        closeForm();
    } catch (e) {
        console.log(e);
        alert('حدث خطأ في الحفظ');
    } finally {
        loading.value = false;
    }
};

const deleteMember = async () => {
    if (!selectedMember.value?.id) return;
    if (!confirm('هل أنت متأكد من الحذف؟')) return;
    loading.value = true;
    try {
        const res = await fetch(
            `/api/family-members/${selectedMember.value.id}`,
            {
                method: 'DELETE',
            },
        );
        if (!res.ok) {
            alert('حدث خطأ في الحذف: ' + res.status);
            return;
        }
        selectedMember.value = null;
        await fetchData();
        closeForm();
    } catch (e) {
        console.log(e);
        alert('حدث خطأ في الحذف');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="شجرة العائلة">
        <link
            href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div style="font-family: 'Tajawal', sans-serif">
        <div
            class="fixed top-0 right-0 left-0 z-10 flex h-14 items-center justify-between bg-slate-900/80 px-4 backdrop-blur-sm md:px-8"
        >
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-white drop-shadow-lg">
                    شجرة العائلة
                </h1>
                <button
                    @click="openForm()"
                    class="rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-600"
                >
                    إضافة جد
                </button>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="zoomIn"
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 text-lg font-bold text-white hover:bg-white/30"
                >
                    +
                </button>
                <span class="min-w-[3rem] text-center text-sm text-white"
                    >{{ Math.round(zoomLevel * 100) }}%</span
                >
                <button
                    @click="zoomOut"
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 text-lg font-bold text-white hover:bg-white/30"
                >
                    −
                </button>
            </div>
        </div>

        <div
            v-if="selectedMember"
            class="fixed top-20 left-1/2 z-20 flex w-full max-w-2xl -translate-x-1/2 items-center justify-between rounded-lg bg-amber-500/20 p-3 text-amber-300 backdrop-blur-sm"
        >
            <div class="flex items-center gap-1">
                <span>تم الاختيار:</span>
                <strong v-if="findParentName(selectedMember.id!)">
                    {{ selectedMember.name }} ({{
                        findParentName(selectedMember.id!)
                    }})
                </strong>
                <strong v-else>{{ selectedMember.name }}</strong>
            </div>
            <div class="flex items-center gap-1">
                <button
                    @click="openFormForUpdate"
                    class="rounded bg-emerald-500 px-3 py-1 text-sm text-white hover:bg-emerald-600"
                >
                    تحديث
                </button>
                <button
                    @click="openFormForAddChild"
                    class="rounded bg-blue-500 px-3 py-1 text-sm text-white hover:bg-blue-600"
                >
                    + ابن/ابنة
                </button>
                <button
                    @click="deleteMember"
                    class="rounded bg-red-500 px-3 py-1 text-sm text-white hover:bg-red-600"
                >
                    حذف
                </button>
                <button
                    @click="selectedMember = null"
                    class="mr-1 text-xl text-amber-300 hover:text-white"
                >
                    ×
                </button>
            </div>
        </div>

        <div
            class="flex items-center justify-center"
            style="
                width: 100vw;
                min-height: 100vh;
                margin-top: 3.5rem;
                background: #1a1a2e;
            "
        >
            <div
                ref="chartContainer"
                style="width: 100vw; height: 100vh; background: #1a1a2e"
            ></div>
        </div>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isFormOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            >
                <div
                    class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-800"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="text-xl font-bold text-slate-800 dark:text-white"
                        >
                            {{
                                formMode === 'edit'
                                    ? 'تعديل البيانات'
                                    : formMode === 'child'
                                      ? 'إضافة ابن/ابنة'
                                      : 'إضافة جد جديد'
                            }}
                        </h2>
                        <button
                            @click="closeForm"
                            class="text-2xl text-slate-400 hover:text-slate-600"
                        >
                            ×
                        </button>
                    </div>

                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div
                            v-if="formMode === 'child'"
                            class="flex items-center gap-2"
                        >
                            <label
                                class="text-sm text-slate-600 dark:text-slate-300"
                                >الشخص الأب:</label
                            >
                            <span
                                class="font-medium text-slate-800 dark:text-white"
                                >{{ selectedMember?.name || 'غير محدد' }}</span
                            >
                            <input type="hidden" v-model="form.parent_id" />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm text-slate-600 dark:text-slate-300"
                                >الاسم *</label
                            >
                            <input
                                v-model="form.name"
                                required
                                class="w-full rounded-lg border border-slate-300 p-3 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm text-slate-600 dark:text-slate-300"
                                >الجنس</label
                            >
                            <select
                                v-model="form.gender"
                                class="w-full rounded-lg border border-slate-300 p-3 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="mb-1 block text-sm text-slate-600 dark:text-slate-300"
                                    >تاريخ الميلاد</label
                                >
                                <input
                                    v-model="form.birth_date"
                                    type="date"
                                    class="w-full rounded-lg border border-slate-300 p-3 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm text-slate-600 dark:text-slate-300"
                                    >تاريخ الوفاة</label
                                >
                                <input
                                    v-model="form.death_date"
                                    type="date"
                                    class="w-full rounded-lg border border-slate-300 p-3 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                            </div>
                        </div>

                        <div v-if="form.gender === 'male'">
                            <label
                                class="mb-1 block text-sm text-slate-600 dark:text-slate-300"
                                >اسم الزوجة</label
                            >
                            <input
                                v-model="form.wife_name"
                                class="w-full rounded-lg border border-slate-300 p-3 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm text-slate-600 dark:text-slate-300"
                                >الوصف</label
                            >
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="w-full rounded-lg border border-slate-300 p-3 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            ></textarea>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button
                                v-if="formMode === 'edit'"
                                type="button"
                                @click="submitForm"
                                :disabled="loading"
                                class="flex-1 rounded-lg bg-emerald-500 py-3 font-medium text-white transition hover:bg-emerald-600 disabled:opacity-50"
                            >
                                {{ loading ? 'جارٍ الحفظ...' : 'تحديث' }}
                            </button>
                            <button
                                v-if="formMode === 'child'"
                                type="button"
                                @click="submitForm"
                                :disabled="loading"
                                class="flex-1 rounded-lg bg-blue-500 py-3 font-medium text-white transition hover:bg-blue-600 disabled:opacity-50"
                            >
                                {{
                                    loading ? 'جارٍ الحفظ...' : 'إضافة ابن/ابنة'
                                }}
                            </button>
                            <button
                                v-if="formMode === 'grandfather'"
                                type="button"
                                @click="submitForm"
                                :disabled="loading"
                                class="flex-1 rounded-lg bg-blue-500 py-3 font-medium text-white transition hover:bg-blue-600 disabled:opacity-50"
                            >
                                {{ loading ? 'جارٍ الحفظ...' : 'إضافة جد' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>
