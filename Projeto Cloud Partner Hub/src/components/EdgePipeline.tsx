import { PartnerData, EdgeStage } from '../types';

interface EdgePipelineProps {
  partners: (PartnerData & { edgeStage: EdgeStage; totalRevenue: number })[];
  onStageClick: (stage: EdgeStage) => void;
  selectedStage: EdgeStage | null;
}

const EdgePipeline = ({ partners, onStageClick, selectedStage }: EdgePipelineProps) => {
  const stages: EdgeStage[] = ['Engage', 'Develop', 'Growth', 'Extend'];
  
  const stageData = stages.map(stage => ({
    stage,
    count: partners.filter(p => p.edgeStage === stage).length,
    revenue: partners.filter(p => p.edgeStage === stage).reduce((sum, p) => sum + p.totalRevenue, 0)
  }));
  
  const stageColors = {
    'Engage': { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-300' },
    'Develop': { bg: 'bg-orange-50', text: 'text-orange-800', border: 'border-orange-300' },
    'Growth': { bg: 'bg-blue-50', text: 'text-blue-800', border: 'border-blue-300' },
    'Extend': { bg: 'bg-purple-50', text: 'text-purple-800', border: 'border-purple-300' }
  };
  
  return (
    <div>
      <h4 className="font-bold text-gray-900 mb-4 flex items-center gap-2">
        <span className="text-gray-700">📊</span>
        Pipeline EDGE
      </h4>
      
      <div className="grid grid-cols-4 gap-3">
        {stageData.map(({ stage, count, revenue }) => {
          const colors = stageColors[stage];
          const isSelected = selectedStage === stage;
          
          return (
            <button
              key={stage}
              onClick={() => onStageClick(stage)}
              className={`
                p-4 rounded-lg border-2 transition-all text-left
                ${colors.bg} ${colors.border} ${colors.text}
                ${isSelected ? 'ring-2 ring-offset-2 ring-blue-700 scale-105' : 'hover:scale-105'}
              `}
            >
              <div className="font-bold text-2xl mb-1">{count}</div>
              <div className="text-xs font-medium mb-2">{stage}</div>
              <div className="text-xs opacity-75">
                ${(revenue / 1000).toFixed(0)}k
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
};

export default EdgePipeline;
