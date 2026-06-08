import React, { useState, useMemo } from 'react';
import {
  Lock, Unlock, Cloud, Shield, Users, Zap, GraduationCap, Building2, Code,
  DollarSign, Megaphone, TrendingUp, Headphones, UserCheck, FileText,
  Star, Video, Award, Sparkles, AlertCircle, ExternalLink, Gift, Trophy
} from 'lucide-react';
import { PartnerData, SolutionArea } from '../types';
import { BENEFITS, Benefit, STAGE_ORDER, CATEGORY_LABELS, CATEGORY_COLORS } from '../data/benefits';
import { calculateEdgeStage } from '../utils/helpers';

export interface BenefitsPageProps {
  partnerData: PartnerData;
}

// Mapa de ícones
const ICON_MAP: Record<string, React.ElementType> = {
  Cloud, Shield, Users, Zap, GraduationCap, Building2, Code,
  DollarSign, Megaphone, TrendingUp, Headphones, UserCheck, FileText,
  Star, Video, Award, Sparkles
};

const BenefitsPage: React.FC<BenefitsPageProps> = ({ partnerData: partner }) => {
  const [selectedCategory, setSelectedCategory] = useState<Benefit['category'] | 'all'>('all');
  const [showOnlyUnlocked, setShowOnlyUnlocked] = useState(false);

  // Calcular score e stage do parceiro
  const partnerPcsScore = partner.pcsScore || 0;
  const partnerStage = calculateEdgeStage(partner);
  const partnerCertCount = partner.certifications ? 
    (Array.isArray(partner.certifications) ? partner.certifications.length :
      (typeof partner.certifications === 'object' ? 
        Object.values(partner.certifications).reduce((a: number, b: any) => a + (Number(b) || 0), 0) : 0)
    ) : 0;

  // Receita total
  const totalRevenue = (partner.revenueM365 || 0) + (partner.revenueAzure || 0) + (partner.revenueSecurity || 0);

  // Verificar se um benefício está desbloqueado
  const isBenefitUnlocked = (benefit: Benefit): boolean => {
    const { requirements } = benefit;
    
    // Verificar área de solução
    if (partner.solutionArea && !benefit.solutionAreas.includes(partner.solutionArea as SolutionArea)) {
      return false;
    }

    // Verificar PCS Score
    if (requirements.minPcsScore && partnerPcsScore < requirements.minPcsScore) {
      return false;
    }

    // Verificar Stage
    if (requirements.minStage) {
      const requiredStageOrder = STAGE_ORDER[requirements.minStage];
      const currentStageOrder = STAGE_ORDER[partnerStage];
      if (currentStageOrder < requiredStageOrder) {
        return false;
      }
    }

    // Verificar certificações
    if (requirements.minCertifications && partnerCertCount < requirements.minCertifications) {
      return false;
    }

    // Verificar receita
    if (requirements.minRevenue) {
      if (totalRevenue < requirements.minRevenue) {
        return false;
      }
    }

    return true;
  };

  // Calcular progresso para desbloquear
  const getUnlockProgress = (benefit: Benefit): { percentage: number; missing: string[] } => {
    const { requirements } = benefit;
    const missing: string[] = [];
    let checks = 0;
    let passed = 0;

    if (requirements.minPcsScore) {
      checks++;
      if (partnerPcsScore >= requirements.minPcsScore) {
        passed++;
      } else {
        missing.push(`PCS Score: ${partnerPcsScore}/${requirements.minPcsScore}`);
      }
    }

    if (requirements.minStage) {
      checks++;
      const requiredStageOrder = STAGE_ORDER[requirements.minStage];
      const currentStageOrder = STAGE_ORDER[partnerStage];
      if (currentStageOrder >= requiredStageOrder) {
        passed++;
      } else {
        missing.push(`Stage: ${partnerStage} → ${requirements.minStage}`);
      }
    }

    if (requirements.minCertifications) {
      checks++;
      if (partnerCertCount >= requirements.minCertifications) {
        passed++;
      } else {
        missing.push(`Certificações: ${partnerCertCount}/${requirements.minCertifications}`);
      }
    }

    if (requirements.minRevenue) {
      checks++;
      if (totalRevenue >= requirements.minRevenue) {
        passed++;
      } else {
        missing.push(`Receita: $${totalRevenue.toLocaleString()}/$${requirements.minRevenue.toLocaleString()}`);
      }
    }

    const percentage = checks > 0 ? Math.round((passed / checks) * 100) : 100;
    return { percentage, missing };
  };

  // Filtrar benefícios
  const filteredBenefits = useMemo(() => {
    return BENEFITS.filter(benefit => {
      // Filtro de categoria
      if (selectedCategory !== 'all' && benefit.category !== selectedCategory) {
        return false;
      }

      // Filtro de área de solução
      if (partner.solutionArea && !benefit.solutionAreas.includes(partner.solutionArea as SolutionArea)) {
        return false;
      }

      // Filtro de desbloqueados
      if (showOnlyUnlocked && !isBenefitUnlocked(benefit)) {
        return false;
      }

      return true;
    });
  }, [selectedCategory, showOnlyUnlocked, partner.solutionArea, partnerPcsScore, partnerStage, partnerCertCount, totalRevenue]);

  // Estatísticas
  const stats = useMemo(() => {
    const relevantBenefits = BENEFITS.filter(b => 
      !partner.solutionArea || b.solutionAreas.includes(partner.solutionArea as SolutionArea)
    );
    const unlocked = relevantBenefits.filter(b => isBenefitUnlocked(b));
    return {
      total: relevantBenefits.length,
      unlocked: unlocked.length,
      percentage: Math.round((unlocked.length / relevantBenefits.length) * 100) || 0
    };
  }, [partner.solutionArea, partnerPcsScore, partnerStage, partnerCertCount, totalRevenue]);

  const categories: Array<Benefit['category'] | 'all'> = ['all', 'tool', 'training', 'funding', 'support', 'marketing'];

  return (
    <div className="p-6">
      {/* Progress Stats */}
      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-100 rounded-xl p-4">
          <div className="flex items-center gap-2 mb-1">
            <Trophy size={18} className="text-yellow-500" />
            <span className="text-sm text-gray-600">Desbloqueados</span>
          </div>
          <p className="text-2xl font-bold text-gray-900">{stats.unlocked}/{stats.total}</p>
          <div className="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div 
              className="h-full bg-gradient-to-r from-purple-500 to-indigo-500 rounded-full transition-all"
              style={{ width: `${stats.percentage}%` }}
            />
          </div>
        </div>
        <div className="bg-gradient-to-r from-blue-50 to-cyan-50 border border-blue-100 rounded-xl p-4">
          <div className="flex items-center gap-2 mb-1">
            <TrendingUp size={18} className="text-blue-500" />
            <span className="text-sm text-gray-600">Seu PCS Score</span>
          </div>
          <p className="text-2xl font-bold text-gray-900">{partnerPcsScore} <span className="text-sm font-normal text-gray-500">pts</span></p>
        </div>
        <div className="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-100 rounded-xl p-4">
          <div className="flex items-center gap-2 mb-1">
            <Star size={18} className="text-amber-500" />
            <span className="text-sm text-gray-600">Seu Stage</span>
          </div>
          <p className="text-2xl font-bold text-gray-900">{partnerStage}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-center justify-between gap-4 flex-wrap mb-6 pb-4 border-b border-gray-200">
        <div className="flex items-center gap-2 flex-wrap">
          {categories.map(cat => (
            <button
              key={cat}
              onClick={() => setSelectedCategory(cat)}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                selectedCategory === cat
                  ? 'bg-purple-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {cat === 'all' ? 'Todos' : CATEGORY_LABELS[cat]}
            </button>
          ))}
        </div>
        
        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={showOnlyUnlocked}
            onChange={(e) => setShowOnlyUnlocked(e.target.checked)}
            className="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
          />
          <span className="text-sm text-gray-600">Mostrar apenas desbloqueados</span>
        </label>
      </div>

      {/* Benefits Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {filteredBenefits.map(benefit => {
          const isUnlocked = isBenefitUnlocked(benefit);
          const { percentage, missing } = getUnlockProgress(benefit);
          const IconComponent = ICON_MAP[benefit.icon] || Gift;

          return (
            <div
              key={benefit.id}
              className={`relative rounded-xl border-2 p-5 transition-all ${
                isUnlocked
                  ? 'border-green-200 bg-green-50/50 hover:shadow-lg hover:border-green-300'
                  : 'border-gray-200 bg-gray-50/50'
              }`}
            >
              {/* Badge NEW */}
              {benefit.isNew && (
                <span className="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full animate-pulse">
                  NOVO
                </span>
              )}

              <div className="flex items-start gap-4">
                {/* Icon */}
                <div className={`w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ${
                  isUnlocked ? 'bg-green-100 text-green-600' : 'bg-gray-200 text-gray-400'
                }`}>
                  {isUnlocked ? <IconComponent size={24} /> : <Lock size={24} />}
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1 flex-wrap">
                    <h3 className={`font-semibold ${isUnlocked ? 'text-gray-900' : 'text-gray-500'}`}>
                      {benefit.name}
                    </h3>
                    <span className={`text-xs px-2 py-0.5 rounded-full border ${CATEGORY_COLORS[benefit.category]}`}>
                      {CATEGORY_LABELS[benefit.category]}
                    </span>
                  </div>
                  
                  <p className={`text-sm mb-3 ${isUnlocked ? 'text-gray-600' : 'text-gray-400'}`}>
                    {benefit.description}
                  </p>

                  {/* Value & Status */}
                  <div className="flex items-center justify-between flex-wrap gap-2">
                    {benefit.value && (
                      <span className={`text-sm font-medium ${isUnlocked ? 'text-green-600' : 'text-gray-400'}`}>
                        {benefit.value}
                      </span>
                    )}

                    {isUnlocked ? (
                      <div className="flex items-center gap-1 text-green-600 text-sm">
                        <Unlock size={14} />
                        <span className="font-medium">Desbloqueado</span>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2">
                        <div className="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            className="h-full bg-orange-400 rounded-full transition-all"
                            style={{ width: `${percentage}%` }}
                          />
                        </div>
                        <span className="text-xs text-gray-500">{percentage}%</span>
                      </div>
                    )}
                  </div>

                  {/* Missing Requirements */}
                  {!isUnlocked && missing.length > 0 && (
                    <div className="mt-3 pt-3 border-t border-gray-200">
                      <p className="text-xs text-gray-500 mb-1">Para desbloquear:</p>
                      <div className="flex flex-wrap gap-1">
                        {missing.map((m, i) => (
                          <span key={i} className="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded">
                            {m}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Action Button */}
                  {isUnlocked && benefit.link && (
                    <a 
                      href={benefit.link} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="mt-3 inline-flex items-center gap-1 text-sm text-purple-600 hover:text-purple-800 hover:underline"
                    >
                      Acessar <ExternalLink size={12} />
                    </a>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {filteredBenefits.length === 0 && (
        <div className="text-center py-12">
          <AlertCircle size={48} className="mx-auto text-gray-300 mb-4" />
          <p className="text-gray-500">Nenhum benefício encontrado com os filtros selecionados.</p>
        </div>
      )}

      {/* Footer tip */}
      <div className="mt-6 p-4 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl border border-purple-100">
        <p className="text-sm text-gray-600 text-center">
          💡 <strong>Dica:</strong> Continue evoluindo seu PCS Score e Stage para desbloquear mais benefícios exclusivos!
        </p>
      </div>
    </div>
  );
};

export default BenefitsPage;
